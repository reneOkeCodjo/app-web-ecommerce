# Bugs Connus

## Bug : Incompatibilité entre le schéma MySQL et le DAO utilisateur

**Fichiers affectés :**

- `database/migrations/001_create_users_table.sql`
- `src/Infrastructure/Persistence/DAO/UserDAO.php`
- `tests/integration/dao/UserDaoTest.php`

**Date de découverte :** 2026-10-04  
**Sévérité :** Critique  
**Statut :** Corrigé

### Description

Le test et le DAO utilisaient les colonnes `username`, `email`, `password` et `id`.
La migration MySQL définit réellement les colonnes suivantes :

| Usage applicatif | Colonne MySQL |
| --- | --- |
| Identifiant | `user_id` |
| Nom d'utilisateur | `user_login` |
| Adresse e-mail | `user_mail` |
| Mot de passe | `user_password` |
| Compte associé | `user_compte_id` |

`user_compte_id` est obligatoire et référence `compte.compte_id`. Un utilisateur
ne peut donc pas être inséré sans créer ou sélectionner un compte associé.

### Symptômes

- L'insertion du jeu de données de `UserDaoTest` échouait sur une colonne
  inconnue ou sur une valeur obligatoire manquante.
- Le DAO échouait ensuite parce qu'il recherchait `username` et lisait des
  clés inexistantes (`id`, `email`, `password`).
- Le nom de la base était codé en dur dans le DAO, ce qui pouvait également
  rendre la configuration `DB_NAME` ineffective.

### Correction appliquée

- `UserDaoTest` crée un enregistrement `compte`, puis insère l'utilisateur avec
  les colonnes du schéma réel.
- `UserDAO` utilise les colonnes MySQL réelles et les alias nécessaires au
  modèle `User`.
- La requête utilise la base sélectionnée par la connexion au lieu de coder
  `site_de_vente`.
- Le test d'intégration DAO passe avec le schéma actuel.

## Bug : Authentification MySQL de l'utilisateur `app2`

**Fichier concerné :** configuration MySQL de l'environnement  
**Date de découverte :** 2026-10-04  
**Sévérité :** Élevée  
**Statut :** À appliquer dans la base de données

### Description

La connexion de l'application peut échouer si l'utilisateur MySQL `app2` est
configuré avec un plugin d'authentification différent de celui attendu par la
version de PHP et l'extension `mysqli`. Le plugin à vérifier est
`mysql_native_password` (et non `mysql_native_passworf`).

Cette modification concerne le compte MySQL utilisé par l'application. Elle ne
doit pas être ajoutée au code source et le mot de passe réel ne doit pas être
commité.

### Vérification

À exécuter avec un compte administrateur MySQL :

```sql
SELECT User, Host, plugin
FROM mysql.user
WHERE User = 'app2';
```

Vérifier également les droits effectifs :

```sql
SHOW GRANTS FOR 'app2'@'%';
```

### Correction à appliquer si nécessaire

Remplacer `CHANGE_ME` par le mot de passe fourni hors du dépôt, puis exécuter
la commande adaptée au `Host` réellement retourné par la vérification :

```sql
ALTER USER 'app2'@'%' IDENTIFIED WITH mysql_native_password BY 'CHANGE_ME';
FLUSH PRIVILEGES;
```

Si le compte est défini pour `localhost`, utiliser
`'app2'@'localhost'` à la place de `'app2'@'%'`. Après la modification,
redémarrer ou recréer le conteneur PHP si nécessaire et relancer les tests de
connexion.

> Remarque : les versions récentes de MySQL peuvent désactiver ou supprimer
> `mysql_native_password`. Dans ce cas, ne pas forcer ce plugin sans vérifier
> la compatibilité de la version MySQL et du client PHP ; mettre plutôt à jour
> le client ou utiliser le plugin supporté par l'environnement.

### Hypothèse initiale concernant les permissions

La première hypothèse était un manque de permissions accordées à `app2`.
Cette hypothèse n'est pas confirmée par la configuration Docker : le service
MySQL crée `DB_NAME`, `DB_USER` et `DB_PASSWORD`, et le compte configuré reçoit
les informations de la base `site_de_vente` via `MYSQL_DATABASE` et
`MYSQL_USER`.

Cela ne remplace pas une vérification avec `SHOW GRANTS`, notamment si le
volume MySQL existait déjà avant une modification des variables
d'environnement : les variables d'initialisation ne réappliquent alors pas
automatiquement les droits. Le plugin d'authentification et les droits
doivent donc être vérifiés séparément.

## Bug : Connexion de login

**Fichier :** `src/Application/Services/Auth.php`
**Date de découverte :** 2026-10-04
**Sévérité :** Critique

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

| Appel                                          | Résultat                                           |
| ---------------------------------------------- | --------------------------------------------------- |
| `$auth = new Auth($repo); $auth->login(...)` | ✅ Fonctionne                                       |
| `Auth::login(...)` (statique)                | ❌`self::$userRepository` = null → Erreur fatale |

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
