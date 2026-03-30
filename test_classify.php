<?php
$python = '/var/www/html/aibotlms/ai/venv/bin/python';
$script = '/var/www/html/aibotlms/ai/scripts/classify.py';

$input = json_encode([
    'sjt'  => ['A','A','A','A','A','B','A','A'],
    'skor' => 5
]);

$input_escaped = escapeshellarg($input);
$output = shell_exec("$python $script $input_escaped 2>&1");

$result = json_decode($output, true);

if ($result && $result['status'] === 'ok') {
    echo "<h3>Klasifikasi berhasil</h3>";
    echo "<p>Level: <strong>{$result['level']}</strong></p>";
    echo "<p>Profil Learning: <strong>{$result['profil_learning']}</strong></p>";
    echo "<p>Profil Gabungan: <strong>{$result['profil_gabungan']}</strong></p>";
    echo "<p>Probabilitas:</p><pre>" . json_encode($result['probabilitas'], JSON_PRETTY_PRINT) . "</pre>";
} else {
    echo "<h3>Error</h3>";
    echo "<pre>$output</pre>";
}
?>
