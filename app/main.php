<?php
error_reporting(E_ALL);

// Uncomment this block to pass the first stage
fwrite(STDOUT, "$ ");

// Wait for user input
$input = trim(fgets(STDIN));
fwrite(stream: STDOUT, data: $input.": command not found");

