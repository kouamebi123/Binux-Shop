<?php

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

// Base de test reconstruite à chaque lancement, par les migrations elles-mêmes :
// les tests tournent donc sur le même schéma et le même catalogue que la production.
(new Filesystem())->remove(dirname(__DIR__).'/var/cache/test');

$kernel = new Kernel('test', false);
$application = new Application($kernel);
$application->setAutoExit(false);

foreach ([
    ['command' => 'doctrine:database:drop', '--force' => true, '--if-exists' => true],
    ['command' => 'doctrine:database:create'],
    ['command' => 'doctrine:migrations:migrate', '--no-interaction' => true],
] as $input) {
    $output = new BufferedOutput();
    if (0 !== $application->run(new ArrayInput($input + ['--quiet' => true]), $output)) {
        fwrite(STDERR, sprintf("Préparation de la base de test impossible (%s) :\n%s\n", $input['command'], $output->fetch()));
        exit(1);
    }
}

$kernel->shutdown();
