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
            } else {
                fwrite(stream: STDOUT, data: $arg . ": not found\n");
            }
            break;
        default:
            fwrite(stream: STDOUT, data: $cmd . ": command not found\n");
    }
}

