<?php

namespace theaddresstechnology\DDD\Helper\Make\Types;

use theaddresstechnology\DDD\Helper\Make\Maker;

class Sync extends Maker
{
    /**
     * Options to be available once Command-Type is called
     *
     * @return Array
     */
    public $options = [];

    /**
     * Return options that should be treated as choices
     *
     * @return Array
     */
    public $allowChoices = [];

    /**
     * Check if the current options is True/False question
     *
     * @return Array
     */
    public $booleanOptions = [];

    /**
     * Check if the current options is requesd based on other option
     *
     * @return Array
     */
    public $requiredUnless = [];


    /**
     * Check if the current options is requesd based on other option
     *
     * @return Array
     */
    public $sync = [];

    /**
     * Fill all placeholders in the stub file
     *
     * @return Boll
     */
    public function service(Array $values):Bool{
        $this->command->error('Sync is not available.');

        return false;
    }

}
