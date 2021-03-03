<?php

namespace App\Components\Log\Formatter;

use Monolog\Formatter\JsonFormatter as BaseJsonFormatter;

/**
 * Class JsonFormatter
 *
 * @package App\Components\Log\Formatter
 * @author Miguel Borges <miguelborges@miguelborges.com>
 */
class JsonFormatter extends BaseJsonFormatter
{

    /**
     * {@inheritdoc}
     */
    public function format(array $record): string
    {

        $user = \Auth::guard('admin')->user();

        $this->includeStacktraces(true);

        $record_new = [
            'time' => $record['datetime']->format('Y-m-d H:i:s'),
            'application' => config('app.name'),
            'environment' => config('app.env'),
            'host' => request()->server('SERVER_ADDR'),
            'remote_address' => request()->server('REMOTE_ADDR'),
            'error_level' => $record['level_name'],
            'message' => $record['message'] ? $record['message'] : "-",
            'url' => \Request::getRequestUri(),
            'request_type' => \Request::method(),
            'user' => object_exists($user) ? $user->name : ''
        ];

        if (!empty($record['extra'])) {
            $record_new['payload']['extra'] = $record['extra'];
        }

        if (!empty($record['context'])) {
            $record_new['payload']['context'] = $record['context'];
        }

        if (!empty($record['context']['exception'])) {
            $ex = $record['context']['exception'];

            if (isset($ex)) {
                $record_new['trace'] = $ex->getTraceAsString();
            }
        }

        $json = $this->toJson($record_new, false) . ($this->appendNewline ? "\n" : '');

        return $json;
    }
}