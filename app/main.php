<?php
error_reporting(E_ALL);

$should_exit = false;
while (!$should_exit) {
    fwrite(STDOUT, "$ ");

    // Wait for user input
    $input = trim(fgets(STDIN));
    $input_array = explode(" ", $input);
    $cmd = $input_array[0];
    switch ($cmd) {
        case "exit":
            $arg = $input_array[1];
            fwrite(stream: STDOUT, data: "exit " . $arg);
            $should_exit = true;
            break;
        default:
            fwrite(stream: STDOUT, data: $cmd . ": command not found\n");
    }
}

