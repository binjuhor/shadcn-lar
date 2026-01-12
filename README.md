# Laravel Shadcn Admin Dashboard

A modern, responsive, and accessible admin dashboard built with Shadcn UI, Laravel, and Vite. This project combines the elegance of Shadcn's UI components with the robustness of Laravel's backend framework, providing a seamless development experience.

![alt text](public/images/shadcn-admin.png)

This project is inspired by [Shadcn-admin](https://github.com/satnaing/shadcn-admin) and adapted to work seamlessly with Laravel and Inertia.js.

## Features

- Light/dark mode
- Responsive
- Accessible
- With built-in Sidebar component
- Global Search Command
- 10+ pages
- Extra custom components

## Tech Stack

**UI:** [ShadcnUI](https://ui.shadcn.com) (TailwindCSS + RadixUI)

**Backend:** [Laravel](https://laravel.com/) 12.x

**Frontend Integration:** [InertiaJs](https://inertiajs.com/)

**Build Tool:** [Vite](https://vitejs.dev/)

**Type Checking:** [TypeScript](https://www.typescriptlang.org/)

**Linting/Formatting:** [Eslint](https://eslint.org/) & [Prettier](https://prettier.io/)

**Icons:** [Tabler Icons](https://tabler.io/icons)

## Run Locally

1. Clone the project

```bash
  git clone git@github.com:binjuhor/shadcn-lar.git
```

2. Go to the project directory

```bash
  cd shadcn-lar
```

3. Install dependencies

- Install JavaScript dependencies:

```bash
  pnpm install
```

- Install PHP dependencies:

```bash
  composer install
```

- Data migration

```bash
  php artisan migrate
```

4. Start the dev
Frotnedend and Backend server
- Start the Vite development server:

```bash
  pnpm run dev
```
- Start the Laravel development server:

```bash
  php artisan serve
```

5. Open your browser and visit http://localhost:8000 to view the dashboard.

## CI/CD Guide

This project includes automated CI/CD workflows using GitHub Actions. The workflows are located in the `.github/workflows/` directory and provide continuous integration and deployment capabilities.

### Available Workflows

#### 1. Tests Workflow (`test.yml`)
Automatically runs on every push to the `main` branch and performs:

- **PHP Setup:** Uses PHP 8.2 with required extensions
- **Environment Setup:** Copies `.env.example` to `.env` and generates application key
- **Dependencies:** Installs Composer dependencies
- **Frontend Build:** Installs Node.js dependencies and builds production assets
- **Database Setup:** Creates SQLite database for testing
- **Test Execution:** Runs PHPUnit/Pest tests (unit and feature tests)

#### 2. Deploy Workflow (`deploy.yml`) 
Automatically deploys to production server on successful pushes to `main` branch:

- **Code Deployment:** Uses rsync to sync code to production server
- **Frontend Build:** Builds production assets before deployment
- **Dependencies:** Installs/updates Composer dependencies via Docker
- **Database Migration:** Runs Laravel migrations
- **Cache Management:** Clears and optimizes application cache
- **Docker Integration:** Restarts Docker containers for updated services

### Required Secrets

For the deployment workflow to work, configure these GitHub repository secrets:

- `PRIVATE_KEY` - SSH private key for server access
- `SSH_HOST` - Production server hostname/IP
- `SSH_USER` - SSH username for server access  
- `WORK_DIR` - Application directory path on server
- `DOCKER_DIR` - Docker compose directory path on server

**Note**: Ensure your server is set up to allow SSH access using the provided private key. Public key should be added to the server's `~/.ssh/authorized_keys`. Folder permissions should allow the SSH user to read/write as needed.`.ssh` folder should have `700` permissions and `authorized_keys` file should have `600` permissions.

### Local Development Workflow

1. **Before Committing:**
   ```bash
   # Run tests locally
   php artisan test
   
   # Build frontend assets
   pnpm run build
   
   # Check code formatting
   pnpm run lint
   ```

2. **Push to Main:**
   - Tests workflow runs automatically
   - If tests pass and on `main` branch, deployment begins
   - Monitor workflow progress in GitHub Actions tab

### Workflow Customization

To modify the CI/CD behavior:

- **Test Configuration:** Edit `.github/workflows/test.yml`
- **Deployment Steps:** Edit `.github/workflows/deploy.yml` 
- **Add Quality Checks:** Consider adding code style checks, static analysis, or security scans

## Modular Architecture

This project uses [nwidart/laravel-modules](https://github.com/nWidart/laravel-modules) for a modular monorepo architecture. Each module is self-contained with its own controllers, models, migrations, and React frontend.

### Available Modules

| Module | Description |
|--------|-------------|
| Finance | Personal finance tracking (accounts, transactions, budgets) |
| Invoice | Invoice management |
| Permission | Roles and permissions management |
| Settings | Application settings |
| Blog | Blog posts and categories |
| Ecommerce | Products, orders, and categories |
| Notification | User notifications |

### Module Commands

#### Create a New Module

```bash
# Basic module scaffolding
php artisan module:scaffold ModuleName

# With CRUD scaffolding (model, migration, policy, controller, pages)
php artisan module:scaffold ModuleName --with-crud

# Specify entity name for CRUD
php artisan module:scaffold Inventory --with-crud --entity=Product

# Preview without creating files
php artisan module:scaffold ModuleName --dry-run
```

#### Generate a Standalone Site from Modules

Extract selected modules into a completely new Laravel project:

```bash
# Create a finance-only site
php artisan site:generate FinanceApp --modules=Finance --output=~/Projects

# Create a multi-module site
php artisan site:generate AdminPanel --modules=Finance,Settings,Permission --output=~/Projects

# Preview what would be created
php artisan site:generate TestApp --modules=Finance --dry-run
```

The `site:generate` command:
- Copies base Laravel+React project structure
- Includes only selected modules
- Updates all configuration files (composer.json, tsconfig.json, vite.config.js)
- Removes unused module references
- Shows next steps after generation

#### Enable/Disable Modules

```bash
# Enable a module
php artisan module:enable ModuleName

# Disable a module
php artisan module:disable ModuleName

# List all modules
php artisan module:list
```

### Module Structure

```
Modules/
└── ModuleName/
    ├── app/
    │   ├── Http/Controllers/
    │   ├── Models/
    │   ├── Policies/
    │   └── Providers/
    ├── config/
    ├── database/
    │   ├── migrations/
    │   └── seeders/
    ├── resources/
    │   └── js/
    │       ├── pages/
    │       └── types/
    ├── routes/
    │   ├── api.php
    │   └── web.php
    └── module.json
```

## Roadmap

Here are some of the planned features for future updates:

- **User Permissions & Roles:** Manage user roles and permissions with a flexible and intuitive system.

- **Profile Manager:** Allow users to update their profiles, including personal information and security settings.

- **Post & Page Manager:** Create and manage dynamic posts and pages with a rich text editor.

- **Theme & Plugin Manager:** Easily install and manage themes and plugins to extend functionality.

- **File & Media Manager:** A powerful file and media manager for handling uploads and organizing assets.



## Author

This project was crafted with 🤍 by [@binjuhor](https://github.com/binjuhor)

## License

This project is open-source and licensed under the [MIT License](https://choosealicense.com/licenses/mit/). Feel free to use, modify, and distribute it as needed.
