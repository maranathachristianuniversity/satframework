<?php
/**
 * satframework.
 * MVC PHP Framework for quick and fast PHP Application Development.
 * Copyright (c) 2020, IT Maranatha
 *
 * @author Didit Velliz
 * @link https://github.com/maranathachristianuniversity/sat-framework
 * @since Version 0.9.3
 */

namespace satframework\peh;

use Exception;
use satframework\Framework;
use satframework\log\LoggerAwareInterface;
use satframework\log\LoggerInterface;
use satframework\log\LogLevel;

/**
 * Class ThrowConsole
 * @package Satframework\peh
 */
class ThrowConsole extends Exception
    implements SatException, LoggerAwareInterface
{

    /**
     * @var string
     */
    var $message;

    /**
     * @var LoggerInterface
     */
    var $logger;

    /**
     * SatException constructor.
     * @param string $message
     */
    public function __construct($message)
    {
        $this->message = $message;
        parent::__construct($message, SatException::console);
    }

    /**
     * @param Exception $error
     */
    public function ExceptionHandler($error)
    {
        $emg['ErrorCode'] = SatException::value;
        $emg['Message'] = $error->getMessage();
        $emg['File'] = $error->getFile();
        $emg['LineNumber'] = $error->getLine();
        $emg['Stacktrace'] = $error->getTrace();

        http_response_code(403);
        header('Author: SAT Framework');
        header('Content-Type: application/json');

        $this->logger->log(LogLevel::ALERT, $error->getMessage(), $emg);

        if (Framework::$factory->getEnvironment() === 'PROD') {
            unset($emg['File']);
            unset($emg['LineNumber']);
            unset($emg['Stacktrace']);
        }
        $data = array(
            'status' => 'error',
            'exception' => $emg
        );

        print_r($data);
        exit(1);
    }

    /**
     * @param $error
     * @param $message
     * @param $file
     * @param $line
     */
    public function ErrorHandler($error, $message, $file, $line)
    {
        $emg['ErrorCode'] = $this->getCode();
        $emg['Message'] = $message;
        $emg['File'] = $file;
        $emg['LineNumber'] = $line;
        $emg['Stacktrace'] = $this->getTrace();

        http_response_code(500);
        header('Author: SAT Framework');
        header('Content-Type: application/json');

        $this->logger->log(LogLevel::ERROR, $message, $emg);

        if (Framework::$factory->getEnvironment() === 'PROD') {
            unset($emg['File']);
            unset($emg['LineNumber']);
            unset($emg['Stacktrace']);
        }
        $data = array(
            'status' => 'error',
            'exception' => $emg
        );

        print_r($data);
        exit(1);
    }

    /**
     * @param LoggerInterface $logger
     * @return mixed
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this->logger;
    }
}