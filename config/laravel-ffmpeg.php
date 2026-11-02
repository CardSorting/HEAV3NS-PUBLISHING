<?php

return [
    'ffmpeg' => [
        'binaries' => env('FFMPEG_BINARIES', 'ffmpeg'),
        'font' => env('FFMPEG_FONT_PATH', 'Verdana'),
        'threads' => 12,   // set to false to disable the default 'threads' filter
    ],
    'ffprobe' => [
        'binaries' => env('FFPROBE_BINARIES', 'ffprobe'),
    ],
    'timeout' => 3600,
    'log_channel' => env('FFMPEG_LOG_CHANNEL', 'ffmpeg'),   // set to false to completely disable logging
    'temporary_files_root' => env('FFMPEG_TEMPORARY_FILES_ROOT', sys_get_temp_dir()),
    'temporary_files_encrypted_hls' => env('FFMPEG_TEMPORARY_ENCRYPTED_HLS', env('FFMPEG_TEMPORARY_FILES_ROOT', sys_get_temp_dir())),
    'set_command_and_error_output_on_exception' => false,
];
