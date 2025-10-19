<?php
error_reporting(E_ALL);

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
            processTypeCmd($input_array[1]);
            break;
        case "pwd":
            $curr_dir = getcwd();
            fwrite(stream: STDOUT, data: $curr_dir . "\n");
            break;
        case "cd":
            navigate($input_array[1]);
            break;
        default:
            $cmd_path = getCmdPath($cmd);
            if ($cmd_path === null) {
                fwrite(stream: STDOUT, data: $cmd . ": command not found\n");
                break;
            }

            // exec exists
            $output = shell_exec($cmd . " " . $input_array[1]);
            fwrite(stream: STDOUT, data: $output);
    }
}


function processTypeCmd(string $cmd): void
{
    $supported_cmd = array("exit", "echo", "type", "pwd", "cd");
    if (in_array($cmd, $supported_cmd)) {
        fwrite(STDOUT, data: $cmd . " is a shell builtin\n");
        return;
    }

    $path = getCmdPath($cmd);
    if ($path === null) {
        fwrite(stream: STDOUT, data: $cmd . ": not found\n");
        return;
    }
    fwrite(stream: STDOUT, data: $cmd . " is " . $path . "\n");
}

function getCmdPath(string $cmd): ?string
{
    $path_var = getenv("PATH");
    if ($path_var === null) {
        return null;
    }
    // paths is just a list of directories
    $paths = explode(PATH_SEPARATOR, $path_var);
    foreach ($paths as $path) {
        $filepath = $path . DIRECTORY_SEPARATOR . $cmd;
        if (file_exists($filepath) && is_executable($filepath)) {
            return $filepath;
        }
    }
    return null;
}

function navigate(string $path): void
{
    $new_path = $path;
    if (str_starts_with($path, "~")) {
        $home = getenv("HOME");
        $new_path = str_replace("~", $home, $new_path);
    }
    if (!is_dir($new_path) || !chdir($new_path)) {
        fwrite(stream: STDOUT, data: "cd: " . $new_path . ": No such file or directory\n");
    }
}