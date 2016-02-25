<?php

/**
 * Abstract class Report, used for defining multiple reports, all the reports should extend from this class
 */
abstract class Report
{
    abstract public function generate();
}
