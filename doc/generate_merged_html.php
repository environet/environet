<?php

$files = scandir(__DIR__);

$mergedMd = '';
foreach ($files as $file) {
	if (!preg_match('/(.*)\.md$/', $file, $m) || $file === 'merged.md') {
		continue;
	}

	$content = file_get_contents(__DIR__.'/'.$file);
	$content = preg_replace('/\(([^\)]+)\.md\)/', '(#$1)', $content);
	$mergedMd .= '<a name="'.($m[1]).'"></a>' . "\n\n" . $content . "\n\n";
}

file_put_contents(__DIR__.'/merged.md', $mergedMd);
// Write to the project readme
file_put_contents(__DIR__.'/../README.md', $mergedMd);

$input = __DIR__.'/merged.md';
$output = __DIR__.'/doc.html';
file_put_contents($output, '');
exec("pandoc $input -o $output");

$layoutFile = __DIR__.'/layout.html';
if (file_exists($output) && file_exists($layoutFile)) {
	$layout = file_get_contents($layoutFile);

	file_put_contents($output, str_replace('{%content%}', file_get_contents($output), $layout));
}

$zipPath = __DIR__.'/environet_doc.zip';
if (file_exists($zipPath)) {
	unlink($zipPath);
}
$zip = new ZipArchive;
if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
	$zip->addFile($output, 'doc.html');
	$zip->addEmptyDir('resources');
	foreach (scandir(__DIR__.'/resources') as $file) {
		if (in_array($file, ['.', '..'])) {
			continue;
		}
		$filePath = __DIR__.'/resources/'.$file;
		$zip->addFile($filePath, 'resources/'.$file);
	}
	$zip->close();
} else {
	echo 'Zip failed';
}
