<?php

// Load database connection, helpers, etc.
require_once(__DIR__ . '/../errors.php');
require_once(__DIR__ . '/../include.php');

require_once(__DIR__ . '/LTVEntry.php');
require_once(__DIR__ . '/Report.php');

/**
 * A LTV report gives a grouped overview of booking counts and turnover for a specific period and the start of those periods.
 * What the report shows is when a booker had their first booking in a specific month, how many bookings and how much turnover the booker generates on average for a specific period (duration).
 * The most important part of the report is the LTV (Life Time Value).
 * This value says that when the first booking happened in a specific month, for a life time of a specific length, what is the actual 'profit' we make on this booker.
 * We define 'profit' as the commission we make over the booking. For this exercise let's say the commission is 10%.
 * This kind of reports are typically run over different periods: 3 months, 12 months and 18 months.
 * So when you run the report over a period of 18 months it shows all bookers who 18 months ago (or longer) had their first booking aggregated per start month.
 */
class CodeAndSqlLTVReport extends Report
{

    /**
     * Period to generate the report for
     *
     * @var int
     */
    protected $period = 12;

    /**
     * The commission per booking
     *
     * @var float
     */
    protected $commission = 0.10;

    /**
     * Constructor
     *
     * @param int $period
     * @param float $commission
     */
    public function __construct($period = 12, $commission = 0.10)
    {
        $this->setPeriod($period);
        $this->setCommission($commission);
    }

    /**
     * Sets the period
     *
     * @param $period Converts the argument to number using coercion
     * @return $this
     */
    public function setPeriod($period)
    {
        $this->period = 0 + $period;
        return $this;
    }

    /**
     * Sets the commission
     *
     * @param $commission Converts the argument to number using coercion`
     * @return $this
     */
    public function setCommission($commission)
    {
        $this->commission = 0 + $commission;
        return $this;
    }


    public function generate()
    {

        global $db;

        $bookings = [];
        $report = [];
        $bookers = [];
        $results = [];

        $sql = <<<SQL
SELECT
  id,
  booker_id
FROM bookings
SQL;

        $stmt = $db->query($sql);

        while ($row = $stmt->fetch()) {
            $bookings[$row->id] = $row->booker_id;
        }

        $sql = <<<SQL
SELECT
  booking_id,
  locked_total_price,
  end_timestamp
FROM bookingitems
  INNER JOIN items ON items.id = bookingitems.item_id
  INNER JOIN spaces ON items.id = spaces.item_id
SQL;

        $stmt = $db->query($sql);

        $timestamp_period = (new DateTime())
            ->sub(new DateInterval('P' . $this->period . 'M'))
            ->modify('midnight')
            ->format("U");

        while ($row = $stmt->fetch()) {

            $booker_id = $bookings[$row->booking_id];
            if (!isset($bookers[$booker_id])) {
                $bookers[$booker_id] = array(
                    "first_booking_timestamp" => null,
                    "first_booking_pretty" => null,
                    "bookings" => [],
                    "locked_total_price" => []
                );
            }

            $booker = &$bookers[$booker_id];

            if (intval($booker['first_booking_timestamp']) > $row->end_timestamp || is_null($booker['first_booking_timestamp'])) {
                $booker['first_booking_timestamp'] = intval($row->end_timestamp);
                $booker['first_booking_pretty'] = date('Y-m', $row->end_timestamp);
            }

            $group = (int)date("Ym", $row->end_timestamp);
            if (!isset($booker['locked_total_price'][$group])) {
                $booker['locked_total_price'][$group] = 0;
            }

            if (!isset($booker['bookings'][$group][$row->booking_id])) {
                $booker['bookings'][$group][$row->booking_id] = 0;
            }

            $booker['bookings'][$group][$row->booking_id] += 1;
            $booker['locked_total_price'][$group] += $row->locked_total_price;

        }

        // Filter out everything and group them into a single report
        foreach ($bookers as $booker_id => $booker) {

            if ($booker['first_booking_timestamp'] > $timestamp_period) {
                continue;
            }

            $year_month = $booker['first_booking_pretty'];
            if (!isset($results[$year_month])) {
                $results[$year_month] = new LTVEntry($year_month, $this->commission);
            }

            $entry = &$results[$year_month];

            // filter out bookings outside of the timestamp_period
            $period_booker_timestamp = (new DateTime('@' . $booker['first_booking_timestamp']))
                ->add(new DateInterval('P' . $this->period . 'M'))
                ->format("Ym");

            $booker_bookings = array();
            $booker_locked_total_price = 0;

            foreach ($booker['bookings'] as $group => $booking) {
                if ($group > $period_booker_timestamp) {
                    continue;
                }
                foreach ($booking as $idx => $val) {
                    $booker_bookings[$idx] = $val;
                }
                $booker_locked_total_price += $booker['locked_total_price'][$group];
            }

            $report[$year_month]['bookers'][$booker_id] = array(
                'bookings' => $booker_bookings,
                'locked_total_price' => $booker_locked_total_price
            );
        }

        // fill up LTVEntry based on $report data
        foreach ($results as $group => &$entry) {

            $year_month_data = &$report[$group];

            $entry->bookings = array_sum(array_map(function ($booker) {
                return count($booker['bookings']);
            }, $year_month_data['bookers']));

            $entry->turnover = array_sum(array_map(function ($booker) {
                return $booker['locked_total_price'];
            }, $year_month_data['bookers']));

            $entry->bookers = count($report[$group]['bookers']);

        }

        // we should sort the keys so it looks better
        uksort($results, function ($a, $b) {
            $a = DateTime::createFromFormat('Y-m', $a);
            $b = DateTime::createFromFormat('Y-m', $b);
            return ($a == $b ? 0 : $a < $b ? -1 : 1);
        });

        return $results;

    }

}
