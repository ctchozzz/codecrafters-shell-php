<?php
error_reporting(E_ALL);

$should_exit = false;
$should_pipe_err = false;
while (!$should_exit) {
    $should_exit = false;
    ;
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
                $output = custom_exec("ls " . $query_path);
                // default write output to file and err to stdout
                $write_content = $output[0];
                $std_out = $output[1];
                if ($should_pipe_err) {
                    // swap
                    list($std_out, $write_content) = array($write_content, $std_out);
                }

                writeToFile($write_content, trim($args[count($args) - 1]));
                fwrite(stream: STDOUT, data: $std_out);
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
                $output = custom_exec("cat " . $query_path);

                // default write output to file and err to stdout
                $write_content = $output[0];
                $std_out = $output[1];
                if ($should_pipe_err) {
                    // swap
                    list($std_out, $write_content) = array($write_content, $std_out);
                }

                writeToFile($write_content, trim($args[count($args) - 1]));
                fwrite(stream: STDOUT, data: $std_out);
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
    } else if (str_contains($arg, "2>")) {
        $mod_arg = str_replace("2>", ">", $mod_arg);
        global $should_pipe_err;
        $should_pipe_err = true;
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
    // trim the end newlines as some cases will have newline at the end to prevent double newline
    $content = rtrim($content, "\n");
    file_put_contents($file_path, $content . "\n");
}

function processTypeEcho(string $arg): void
{
    $args = parseRedirects($arg);
    $str = processQuotedStr(trim($args[0]));
    if (count($args) == 1) {
        fwrite(stream: STDOUT, data: $str . "\n");
        return;
    }

    $file_path = $args[1];
    global $should_pipe_err;
    // redirect to file
    if ($should_pipe_err) {
        // echo has no error output so just write content to stdout & create empty file
        fwrite(stream: STDOUT, data: $str . "\n");
        writeToFile("", $file_path);
        return;
    }

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

function custom_exec(string $cmd): array
{
    $descriptorspec = array(
        0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
        1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
        2 => array("pipe", "w")   // stderr is a pipe that the child will write to
    );

    $stdout = null;
    $stderr = null;
    $process = proc_open($cmd, $descriptorspec, $pipes);
    if (is_resource($process)) {
        fclose($pipes[0]); // Close stdin pipe

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        proc_close($process);
    }
    return array($stdout, $stderr);
}