<?php

use putyourlightson\blitz\controllers\DiagnosticsController;
use yii\web\NotFoundHttpException;

test('Diagnostics paths cannot escape the diagnostics templates directory', function(string $path) {
    $controller = new DiagnosticsController('diagnostics', Craft::$app);

    expect(fn() => $controller->actionIndex($path))
        ->toThrow(NotFoundHttpException::class);
})->with([
    '../report',
    '../../../Blitz.php',
    '..\\..\\..\\Blitz.php',
    'pages/../../../../Blitz.php',
]);
