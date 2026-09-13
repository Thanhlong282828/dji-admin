<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand;
use RuntimeException;

final class ProjectServeCommand extends ServeCommand
{
    public const UPLOAD_MAX_FILESIZE = '12M';

    public const POST_MAX_SIZE = '14M';

    /** @return list<string> */
    protected function serverCommand(): array
    {
        $command = parent::serverCommand();

        array_splice($command, 1, 0, [
            '-d',
            'upload_max_filesize='.self::UPLOAD_MAX_FILESIZE,
            '-d',
            'post_max_size='.self::POST_MAX_SIZE,
        ]);

        $arguments = array_values($command);
        foreach ($arguments as $argument) {
            if (! is_string($argument)) {
                throw new RuntimeException('PHP development server command contains an invalid argument.');
            }
        }

        return $arguments;
    }
}
