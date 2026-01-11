<?php
error_reporting(E_ALL);

$should_exit = false;
while (!$should_exit) {
    fwrite(STDOUT, "$ ");

    // Wait for user input
    $input = trim(fgets(STDIN));
    $input_array = explode(" ", $input, 2);
    if ($input[0] === "\"" || $input[0] === "'") {
        $quote = $input[0];
        $last_quote_pos = strrpos($input, $quote);
        $str_cmd = substr($input, 0, $last_quote_pos + 1);
        $input_array[0] = processQuotedStr($str_cmd);
        $input_array[1] = substr($input, $last_quote_pos + 1);
    }

    $cmd = $input_array[0];
    switch ($cmd) {
        case "exit":
            $arg = $input_array[1];
            $should_exit = true;
            break;
        case "echo":
            processTypeEcho($input_array[1]);
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
        case "ls":
            // redirect to file
            if (count($input_array) > 1) {
                $args = parseRedirects($input_array[1]);
                $query_path = "";
                if (count($args) > 1) {
                    $query_path = trim($args[0]);
                }
                $content = shell_exec("ls " . $query_path);
                writeToFile($content, trim($args[count($args) - 1]));
                break;
            }
            // to stdout
            exec_cmd($cmd, $input_array[1] ?? "");
            break;
        case "cat":
            $args = parseRedirects($input_array[1]);
            $query_path = "";
            // redirect to file
            if (count($args) > 1) {
                $query_path = trim($args[0]);
                $content = shell_exec("cat " . $query_path);
                if ($content !== false && $content !== null) {
                    writeToFile($content, trim($args[count($args) - 1]));
                } else {
                    fwrite(stream: STDOUT, data: $output);
                }
            } else {
                // to stdout
                exec_cmd($cmd, $input_array[1] ?? "");
            }
            break;
        default:
            exec_cmd($cmd, $input_array[1] ?? "");
    }
}

function exec_cmd(string $cmd, string $arg): void
{
    $cmd_path = getCmdPath($cmd);
    if ($cmd_path === null) {
        fwrite(stream: STDOUT, data: $cmd . ": command not found\n");
        return;
    }

    // exec exists
    $closing_quote = "'";
    if (str_contains($cmd_path, "\'")) {
        $closing_quote = "\"";
    }
    $new_cmd = $closing_quote . $cmd . $closing_quote . " " . $arg;
    $output = shell_exec($new_cmd);
    fwrite(stream: STDOUT, data: $output);
}

function parseRedirects(string $arg): array
{
    $mod_arg = $arg;
    if (str_contains($arg, "1>")) {
        $mod_arg = str_replace("1>", ">", $mod_arg);
    }

    $delimiter_pos = strrpos($mod_arg, ">");
    if ($delimiter_pos === false) {
        return [$arg];
    }

    // redirect
    $new_arg = substr($mod_arg, 0, $delimiter_pos);
    $file_path = substr($mod_arg, $delimiter_pos + 1);
    return [trim($new_arg), trim($file_path)];
}

function writeToFile(string $content, string $file_path)
{
    file_put_contents($file_path, $content);
}

function processTypeEcho(string $arg): void
{
    $args = parseRedirects($arg);
    $str = processQuotedStr(trim($args[0]));
    if (count($args) == 1) {
        fwrite(stream: STDOUT, data: $str . "\n");
        return;
    }

    // redirect to file
    $file_path = $args[1];
    writeToFile($str, $file_path);
    return;
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

function processQuotedStr(string $str): string
{
    $res = "";
    $curr_quote = "";
    $is_escaped = false;
    $supported_escaped_char = array("\"", "\\");
    for ($i = 0; $i < strlen($str); $i++) {
        $char = $str[$i];
        switch ($char) {
            case "\"":
            case "'":
                if ($is_escaped) {
                    $res .= $char;
                    $is_escaped = false;
                    break;
                }

                if (empty($curr_quote)) {
                    // opening quote
                    $curr_quote = $char;
                    break;
                } elseif ($curr_quote === $char) {
                    // closing quote
                    $curr_quote = "";
                    break;
                }
                $res .= $char;
                break;
            case " ":
                // collapse spaces for unquoted unescaped string
                if (empty($curr_quote) && !$is_escaped && $i > 0 && $str[$i - 1] === " ") {
                    break;
                }
                $res .= $char;
                $is_escaped = false;
                break;
            case "\\":
                if ($is_escaped) {
                    $res .= $char;
                    $is_escaped = false;
                    break;
                }

                // only allow escape for no quote or double quote
                if (empty($curr_quote) || $curr_quote === "\"" && $i + 1 < strlen($str) && in_array($str[$i + 1], $supported_escaped_char)) {
                    $is_escaped = true;
                    break;
                }
                $res .= $char;
                break;
            default:
                $is_escaped = false;
                $res .= $char;
        }
    }
    return $res;
}