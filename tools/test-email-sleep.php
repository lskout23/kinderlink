<?php
// Local rendering regression test: no config, database or email transport.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../controllers/MessagesController.php';

$class = new ReflectionClass(MessagesController::class);
$controller = $class->newInstanceWithoutConstructor();
$render = $class->getMethod('buildEmailBody');
$cases = [
    ['<td>|*sleep*| λεπτά</td>', 'Όχι', '<td>Όχι</td>'],
    ['<td>|*sleep*| Λεπτά</td>', 'Όχι', '<td>Όχι</td>'],
    ['<td>|*sleep*| &lambda;&epsilon;&pi;&tau;ά</td>', 'Όχι', '<td>Όχι</td>'],
    ['<td>|*sleep*| &lambda;&epsilon;&pi;&tau;&#940;</td>', 'Όχι', '<td>Όχι</td>'],
    ['<td>|*sleep*| &#955;&#949;&#960;&#964;&#940;</td>', 'Όχι', '<td>Όχι</td>'],
    ['<td>|*sleep*| &#x3bb;&#x3b5;&#x3c0;&#x3c4;&#x3ac;</td>', 'Όχι', '<td>Όχι</td>'],
    ['<td>|*sleep*| &lambda;&epsilon;&pi;&tau;ά</td>', '30', '<td>30 &lambda;&epsilon;&pi;&tau;ά</td>'],
    ['<td>|*sleep*| λεπτάκια</td>', 'Όχι', '<td>Όχι λεπτάκια</td>'],
    ['<p>&lt;b&gt;</p><td>|*sleep*| &lambda;&epsilon;&pi;&tau;ά</td>', 'Όχι', '<p>&lt;b&gt;</p><td>Όχι</td>'],
    ['<td>|*sleep*|&nbsp;λεπτά</td>', 'Όχι', '<td>Όχι</td>'],
    ['<td>|*sleep*|&#160;λεπτά</td>', 'Όχι', '<td>Όχι</td>'],
    ['<td>|*sleep*|&#xA0;λεπτά</td>', 'Όχι', '<td>Όχι</td>'],
    ["<td>|*sleep*|\u{00A0}λεπτά</td>", 'Όχι', '<td>Όχι</td>'],
    ['<td>|*sleep*|</td>', 'Όχι', '<td>Όχι</td>'],
    ['<td>|*sleep*| λεπτά</td>', '30', '<td>30 λεπτά</td>'],
    ['<td>|*sleep*| λεπτά</td>', '1', '<td>1 λεπτά</td>'],
    ['<td>|*sleep*|</td>', '60', '<td>60</td>'],
    ['<td>|*sleep*| λεπτά</td><p>|*comments*|</p>', 'Όχι', '<td>Όχι</td><p>Όχι λεπτά — αμετάβλητο σχόλιο</p>'],
];

foreach ($cases as $index => [$template, $sleep, $expected]) {
    $actual = $render->invoke($controller, $template, [
        '|*sleep*|' => $sleep,
        '|*comments*|' => 'Όχι λεπτά — αμετάβλητο σχόλιο',
    ]);
    if ($actual !== $expected) {
        fwrite(STDERR, 'FAIL: case ' . ($index + 1) . PHP_EOL);
        exit(1);
    }
}
echo 'PASS: ' . count($cases) . ' sleep rendering cases; no database or email calls.' . PHP_EOL;