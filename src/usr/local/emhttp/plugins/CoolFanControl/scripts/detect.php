<?php
/*
    Copyright (c) 2023, Thierry Tremblay
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice, this
    list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright notice,
    this list of conditions and the following disclaimer in the documentation
    and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
    AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
    IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
    DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
    FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
    DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
    SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
    CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
    OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
    OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

// Chips can be mounted to different locations on each reboot.
// To woakaround this we need to use the chip name as our device key and not persist it.

function exec_command($command) {
    $raw = trim(shell_exec($command));
    return empty($raw) ? [] : explode("\n", $raw);
}

function detect_sensors($path, $type) {
    $pathnames = exec_command("find ${path}/${type}[0-9]_input");
    return array_map(fn($pathname) => array(
        "name" => basename($pathname),
        "type" => $type,
        "input" => $pathname,
    ), $pathnames);
}


function detect_chip($pathname) {
    $path = dirname($pathname);
    return [
        "name" => file_get_contents($pathname),
        "path" => $path,
        "realpath" => realpath($path),      # TODO: this is nice, but do we care / need it for anything?
        "sensors" => detect_sensors($path, "fan") + detect_sensors($path, "temp")
    ];
}


function detect_chips() {
    $pathnames = exec_command("find /sys/class/hwmon/hwmon*/name -follow -maxdepth 1 -type f");
    $chips = array_map(fn($pathname) => detect_chip($pathname), $pathnames);
    $chips = array_filter($chips, fn($chip) => !empty($chip["sensors"]));
    return $chips;
}


function detect_disks() {
    $devices = exec_command("find /sys/block/{[hs]d*[a-z],nvme[0-9a-f]*}");
    $disks = [];
    foreach($devices as $device) 
    {
        $disks[basename($device)] = [
            "path" => realpath($device),
        ];
    }

    $hwmons = array_map(fn($path) => realpath($path), exec_command("find /sys/class/hwmon/*"));
    foreach($hwmons as $hwmon) {
        $prefix = $hwmon; 
        $index = strpos($prefix, "/hwmon");
        if ($index) $prefix = substr($prefix, 0, $index);
        foreach($disks as &$disk) {
            if (str_starts_with($disk["path"], $prefix)) {
                $disk["hwmon"] = $hwmon;
                $disk["chip"] = file_get_contents($hwmon."/name");
            }
        }
    }

    return $disks;
}

echo print_r(detect_disks(), true);
?>
