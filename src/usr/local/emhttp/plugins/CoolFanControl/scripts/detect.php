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
    $raw = trim(shell_exec($command." 2>nul"));
    return empty($raw) ? [] : explode("\n", $raw);
}

function detect_chips() {
    $pathnames = exec_command("find /sys/class/hwmon/hwmon*/name -follow -maxdepth 1 -type f");

    $chips = [];
    $counts = [];
    foreach($pathnames as $key => $pathname) {
        $name = trim(file_get_contents($pathname));
        $counts[$name] += 1;
        $id = $name."-".$counts[$name];
        $chips[$id] = [
            "id" => $id,
            "path" => realpath(dirname($pathname)),
            "name" => $name,
        ];
    }

    return $chips;
}

function detect_disks() {
    $devices = exec_command("find /sys/block/{[hs]d*[a-z],nvme[0-9a-f]*}");
    $disks = [];
    foreach($devices as $device) {
        $id = basename($device);
        $disks[$id] = [
            "id" => $id,
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
                $disk["chip"] = $hwmon;
            }
        }
    }

    return $disks;
}

function detect_fans(&$chips) {
    $fans = [];
    foreach($chips as &$chip) {
        $chip["fans"] = [];
        $paths = exec_command("find ".$chip["path"]."/pwm[0-9]");
        foreach($paths as $path) {
            $id = $chip["id"]."-".basename($path);
            $fan = [
                "id" => $id,
                "path" => $path,
                "chip" => $chip["id"],
                "label" => trim(file_get_contents($path."_label")) ?: basename($path),
            ];
            $fans[$id] = $fan;
            $chip["fans"][$id] = $fan;
        }
    }
    return $fans;
}

function detect_sensors(&$chips) {
    $sensors = [];
    foreach($chips as &$chip) {
        $chip["sensors"] = [];
        foreach(['fan', 'temp'] as $type) {
            $inputs = exec_command("find ".$chip["path"]."/".$type."[0-9]_input");
            foreach($inputs as $input) {
                $path = substr($input, 0, -6);
                $id = $chip["id"]."-".basename($path);
                $sensor = [
                    "id" => $id,
                    "type" => $type,
                    "path" => $input,
                    "chip" => $chip["id"],
                    "label" => trim(file_get_contents($path."_label")) ?: basename($path),
                ];
                $sensors[$id] = $sensor;
                $chip["sensors"][$id] = $sensor;
            }
        }
    }
    return $sensors;
}

$chips = detect_chips();
$disks = detect_disks();
$fans = detect_fans($chips);
$sensors = detect_sensors($chips);
?>
