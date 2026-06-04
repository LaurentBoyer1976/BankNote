# Symfony-App (application de suivi de budget)

Ce dossier contient le squelette Symfony 5 pour le TP 6.3.3.

## Demarrage rapide

1. Installer les dependances
2. Lancer le serveur de dev Symfony
3. Lancer les tests

## Commandes utiles (PowerShell)

```powershell
Set-Location "C:\Users\Laurent\Formation_dev\LPDWCA\UE 6.3.1 Frameworks et approfondissement web\UE 6.3.3 Symfony-MCV\Symfony-App"
composer install
php -S localhost:8000 -t public
php bin/phpunit
```

## Points de depart

- Route d'accueil: `App\Controller\HomeController` (GET /)
- Template: `templates/home/index.html.twig`
- Test: `tests/SmokeTest.php`
