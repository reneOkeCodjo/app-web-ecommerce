# Bugs Connus

## Bug : Connexion de login

**Fichier :** `src/Application/Services/Auth.php`
**Date de découverte :** 2026-10-04
**Sévérité :** Critique

---

### Description

La méthode `login()` est déclarée `static` dans l'interface `AuthInterface` et la classe `Auth`, mais elle utilise `self::$userRepository` qui est initialisé dans le constructeur.

### Problème

```php
// AuthInterface.php
public static function login(string $username, string $password): bool;

// Auth.php
class Auth implements AuthInterface {
    private static UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository) {
        self::$userRepository = $userRepository;  // Initialisé ici
    }

    public static function login(string $username, string $password): bool {
        $user = self::$userRepository->findByUsername($username);  // ← BUG
    }
}
```

### Conséquences

| Appel | Résultat |
|---|---|
| `$auth = new Auth($repo); $auth->login(...)` | ✅ Fonctionne |
| `Auth::login(...)` (statique) | ❌ `self::$userRepository` = null → Erreur fatale |

### Fichiers affectés

- `src/Application/Services/Auth.php`
- `tests/unit/service/AuthServiceTest.php`

### Correction proposée

**Option 1 : Rendre `login()` non-static**
```php
// Interface
public function login(string $username, string $password): bool;

// Classe
public function login(string $username, string $password): bool {
    $user = $this->userRepository->findByUsername($username);
    // ...
}
```

**Option 2 : Injecter le repository en paramètre**
```php
public static function login(string $username, string $password, UserRepositoryInterface $repo): bool
```

### Statut

- [ ] Non corrigé
- [ ] En cours de correction
- [ ] Corrigé
