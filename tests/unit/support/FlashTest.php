<?php

// On impose des types stricts pour éviter les conversions silencieuses.
declare(strict_types=1);

// On place ce test dans le dossier « support ».
namespace Tests\Unit\Support;

// On teste la classe Flash.
use App\Application\Support\Flash;
use App\Infrastructure\Http\SessionStoreInterface;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie que les messages flash sont bien stockés puis effacés.
 */
final class FlashTest extends TestCase
{
    public function testAddThenConsumeReturnsTheMessageOnce(): void
    {
        // On prépare une session en mémoire.
        $session = $this->memorySession();

        // On ajoute un message de succès.
        Flash::add($session, 'success', 'Produit créé.');

        // La première lecture renvoie le message.
        $first = Flash::consume($session);
        self::assertSame(
            [['type' => 'success', 'message' => 'Produit créé.']],
            $first
        );

        // La seconde lecture est vide : le message est bien effacé.
        self::assertSame([], Flash::consume($session));
    }

    public function testUnknownTypeFallsBackToInfo(): void
    {
        // On prépare une session en mémoire.
        $session = $this->memorySession();

        // On ajoute un message avec un type qui n'existe pas.
        Flash::add($session, 'alerte', 'Information.');

        // Le type est remplacé par « info ».
        self::assertSame(
            [['type' => 'info', 'message' => 'Information.']],
            Flash::consume($session)
        );
    }

    public function testEmptyMessageIsIgnored(): void
    {
        // On prépare une session en mémoire.
        $session = $this->memorySession();

        // Un message vide ne doit pas être affiché.
        Flash::add($session, 'success', '');

        self::assertSame([], Flash::consume($session));
    }

    private function memorySession(): SessionStoreInterface
    {
        // On crée une petite session qui garde tout en mémoire.
        return new class implements SessionStoreInterface {
            /** @var array<string, mixed> */
            private array $values = [];

            public function set(string $key, mixed $value): void
            {
                $this->values[$key] = $value;
            }

            public function get(string $key): mixed
            {
                return $this->values[$key] ?? null;
            }

            public function remove(string $key): void
            {
                unset($this->values[$key]);
            }

            public function regenerate(): void
            {
            }

            public function destroy(): void
            {
                $this->values = [];
            }
        };
    }
}
