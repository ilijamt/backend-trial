<?php

class LTVEntry
{

    public $month;
    public $commission;
    public $bookers_ids = null;
    public $bookers = 0;
    public $bookings = 0;
    public $turnover = 0;
    protected $isDirty = false;

    /**
     * LTVEntry constructor.
     *
     * @param $month
     * @param $commission
     */
    public function __construct($month, $commission)
    {
        $this->month = $month;
        $this->commission = $commission;
    }

    public function addBookerId($booker_id)
    {
        if (is_null($this->bookers_ids)) {
            $this->bookers_ids = array();
        }
        $this->isDirty = true;
        $this->bookers_ids[$booker_id] = true;
    }

    public function getFormattedDate()
    {
        return DateTime::createFromFormat('Y-m', $this->month)->format("M Y");
    }

    public function getBookings()
    {
        return $this->bookings;
    }

    public function getAverageBookings()
    {
        if ($this->getBookers() <= 0) {
            return 0;
        }
        return $this->bookings / $this->getBookers();
    }

    public function getBookers()
    {
        if ($this->isDirty) {
            $this->isDirty = false;
            $this->bookers = count(array_keys($this->bookers_ids));
        }
        return $this->bookers;
    }

    public function getLTV()
    {
        return $this->commission * $this->getAverageTurnover();
    }

    public function getAverageTurnover()
    {
        if ($this->bookings <= 0) {
            return 0;
        }
        return $this->turnover / $this->bookings;
    }

    public function getTurnover()
    {
        return $this->turnover;
    }

}
