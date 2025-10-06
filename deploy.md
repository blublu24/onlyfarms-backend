# Backend Deployment Guide

## 1. Prepare for Railway Deployment

### Install Railway CLI
```bash
npm install -g @railway/cli
```

### Login to Railway
```bash
railway login
```

### Initialize Railway Project
```bash
cd C:\xampp\htdocs\onlyfarmsbackend
railway init
```

### Set Environment Variables
```bash
# Set production environment variables
railway variables set APP_ENV=production
railway variables set APP_DEBUG=false
railway variables set APP_URL=https://your-app.railway.app

# Database (if using Railway PostgreSQL)
railway variables set DB_CONNECTION=pgsql
railway variables set DB_HOST=${{Postgres.PGHOST}}
railway variables set DB_PORT=${{Postgres.PGPORT}}
railway variables set DB_DATABASE=${{Postgres.PGDATABASE}}
railway variables set DB_USERNAME=${{Postgres.PGUSER}}
railway variables set DB_PASSWORD=${{Postgres.PGPASSWORD}}

# Or if using Supabase
railway variables set DB_CONNECTION=pgsql
railway variables set DB_HOST=your-supabase-host
railway variables set DB_PORT=5432
railway variables set DB_DATABASE=your-database
railway variables set DB_USERNAME=your-username
railway variables set DB_PASSWORD=your-password

# Generate app key
railway run php artisan key:generate
```

### Deploy
```bash
railway up
```

## 2. Database Setup

### Run Migrations
```bash
railway run php artisan migrate
```

### Seed Database (Optional)
```bash
railway run php artisan db:seed
```

## 3. Storage Setup

### Create Storage Link
```bash
railway run php artisan storage:link
```

## 4. Get Your Production URL
```bash
railway domain
```

Copy the URL and update your frontend configuration.
