<?php

namespace App\Upload;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\NamerInterface;

/**
 * Nom de fichier tiré au hasard, avec l'extension déduite du contenu réel du fichier :
 * le nom et l'extension envoyés par le navigateur ne sont jamais repris.
 */
final class RandomNamer implements NamerInterface
{
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function name(object $object, PropertyMapping $mapping): string
    {
        $file = $mapping->getFile($object);
        $extension = $file instanceof UploadedFile ? strtolower((string) $file->guessExtension()) : '';

        if (!\in_array($extension, self::EXTENSIONS, true)) {
            throw new \RuntimeException('Type de fichier refusé.');
        }

        return bin2hex(random_bytes(12)) . '.' . $extension;
    }
}
