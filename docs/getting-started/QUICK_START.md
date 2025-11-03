# VTPHP Framework - Quick Start

## 🚀 Get Started in 5 Minutes

### Step 1: Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies (Tailwind + Vite)
npm install
```

### Step 2: Configure Environment

```bash
# Copy environment file
copy .env.example .env

# Generate application key
php artisan key:generate
```

Edit `.env` and set your database credentials:

```env
APP_NAME="VTPHP Framework"
DB_HOST=localhost
DB_DATABASE=vtphp_db
DB_USERNAME=root
DB_PASSWORD=your_password
```

### Step 3: Setup Database

```bash
# Create database (MySQL example)
mysql -u root -p -e "CREATE DATABASE vtphp_db"

# Run migrations
php artisan migrate
```

### Step 4: Start Development

Open two terminals:

**Terminal 1: PHP Server**

```bash
php artisan serve
```

**Terminal 2: Vite Dev Server (for Tailwind CSS)**

```bash
npm run dev
```

### Step 5: Open Your Browser

Visit: **http://localhost:8000**

---

## 🎨 Build for Production

When ready to deploy:

```bash
# Build optimized assets
npm run build

# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize
php artisan optimize
```

---

## 📚 Learn More

- Read `README.md` for detailed documentation
- Check `VTPHP_COMPLETE_GUIDE.md` for comprehensive guide
- Browse `docs/` folder for tutorials

---

## 🛠️ Common Commands

```bash
# Create controller
php artisan make:controller UserController --resource

# Create model with migration
php artisan make:model Post --migration

# Create middleware
php artisan make:middleware CheckAdmin

# List all routes
php artisan route:list

# List all commands
php artisan list
```

---

## ❓ Troubleshooting

**Can't connect to database?**

- Check `.env` database credentials
- Make sure MySQL/PostgreSQL is running
- Verify database exists

**Assets not loading?**

- Run `npm run dev` in separate terminal
- Check if Vite dev server is running on port 5173

**Permission errors?**

- Make sure `storage/` and `bootstrap/cache/` are writable
- On Linux/Mac: `chmod -R 775 storage bootstrap/cache`

---

**🎉 Happy coding with VTPHP Framework!**
