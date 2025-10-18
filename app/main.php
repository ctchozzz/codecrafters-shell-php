<?php
error_reporting(E_ALL);

while (true) {
    fwrite(STDOUT, "$ ");
    
    // Wait for user input
    $input = trim(fgets(STDIN));
    fwrite(stream: STDOUT, data: $input.": command not found\n");
}

