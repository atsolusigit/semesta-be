<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Constructor for the base controller.
     */
    public function __construct()
    {
        // Initialization code can go here if needed
    }

    /**
     * A method to handle common functionality across controllers.
     *
     * @param string $message
     * @return string
     */
    protected function respond($message)
    {
        return "Response: " . $message;
    }
}
