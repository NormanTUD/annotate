// --- Step 3: Run YOLO training ---
echo "\n🏋️ Step 3: Starting YOLO training ($epochs epochs, model: $model_yaml)...\n";
flush();

// Use a fixed, writable path for the virtualenv
$venv_path = '/tmp/.yolov11_venv';
$activate = "$venv_path/bin/activate";
$pip = "$venv_path/bin/pip";
$python = "$venv_path/bin/python";

if (!is_file($activate)) {
    echo "   Creating virtualenv at $venv_path...\n";
    flush();
    
    // Create venv
    $venv_output = [];
    exec("python3 -m venv " . escapeshellarg($venv_path) . " 2>&1", $venv_output, $venv_exit);
    echo "   " . implode("\n   ", $venv_output) . "\n";
    flush();
    
    if ($venv_exit !== 0 || !is_file($activate)) {
        die("❌ Error: Failed to create virtualenv at $venv_path (exit code: $venv_exit)\n</pre></body></html>");
    }
    
    // Install packages using the venv's pip directly (no need for 'source')
    echo "   Installing ultralytics and dependencies...\n";
    flush();
    $install_cmd = escapeshellarg($pip) . " install ultralytics onnx2tf tf_keras onnx_graphsurgeon sng4onnx 2>&1";
    
    $install_process = proc_open($install_cmd, [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $install_pipes);
    if (is_resource($install_process)) {
        stream_set_blocking($install_pipes[1], false);
        stream_set_blocking($install_pipes[2], false);
        while (true) {
            $line1 = fgets($install_pipes[1]);
            $line2 = fgets($install_pipes[2]);
            if ($line1 !== false) { echo "   " . htmlspecialchars($line1); flush(); }
            if ($line2 !== false) { echo "   " . htmlspecialchars($line2); flush(); }
            $st = proc_get_status($install_process);
            if (!$st['running'] && $line1 === false && $line2 === false) break;
        }
        fclose($install_pipes[1]);
        fclose($install_pipes[2]);
        $install_exit = proc_close($install_process);
        if ($install_exit !== 0) {
            die("❌ Error: pip install failed (exit code: $install_exit)\n</pre></body></html>");
        }
    }
    echo "   ✅ Virtualenv ready.\n";
    flush();
} else {
    echo "   Using existing virtualenv at $venv_path\n";
    flush();
}

$imgsz = $GLOBALS["imgsz"] ?? 800;

// Use the venv's yolo binary directly instead of 'source activate'
$yolo_bin = "$venv_path/bin/yolo";

$train_cmd = escapeshellarg($yolo_bin) . " detect train"
    . " data=" . escapeshellarg("$tmp_dir/dataset.yaml")
    . " model=" . escapeshellarg($model_yaml)
    . " epochs=$epochs"
    . " batch=16"
    . " imgsz=$imgsz"
    . " project=" . escapeshellarg("$tmp_dir/runs")
    . " 2>&1";

echo "   Command: $train_cmd\n\n";
flush();

// Stream training output
$descriptorspec = [
    1 => ["pipe", "w"],
    2 => ["pipe", "w"]
];

$process = proc_open($train_cmd, $descriptorspec, $pipes);
