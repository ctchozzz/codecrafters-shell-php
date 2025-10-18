<?php
error_reporting(E_ALL);

$supported_cmd = array("exit", "echo", "type");
$should_exit = false;
while (!$should_exit) {
    fwrite(STDOUT, "$ ");

    // Wait for user input
    $input = trim(fgets(STDIN));
    $input_array = explode(" ", $input, 2);
    $cmd = $input_array[0];
    switch ($cmd) {
        case "exit":
            $arg = $input_array[1];
            $should_exit = true;
            break;
        case "echo":
            $arg = $input_array[1];
            fwrite(stream: STDOUT, data: $arg . "\n");
            break;
        case "type":
            $arg = $input_array[1];
            if (in_array($arg, $supported_cmd)) {
                fwrite(STDOUT, data: $arg . " is a shell builtin\n");
                break;
            }

            $path = getCmdPath($arg);
            if ($path === null) {
                fwrite(stream: STDOUT, data: $arg . ": not found\n");
                break;
            }
            fwrite(stream: STDOUT, data: $arg . " is " . $path . "\n");
            break;
        default:
            fwrite(stream: STDOUT, data: $cmd . ": command not found\n");
    }
}


function getCmdPath(string $cmd): ?string
{
    $path_var = getenv("PATH");
    if ($path_var === null) {
        return null;
    }
    $paths = explode(PATH_SEPARATOR, $path_var);
    foreach ($paths as $path) {
        if (!str_ends_with($path, $cmd)) {
            continue;
        }
        return $path;
        // if (file_exists($path)) {
        //     return $path;
        // }
    }
    return null;
}