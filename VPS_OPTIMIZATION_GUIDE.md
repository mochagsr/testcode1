# VPS Optimization Guide (2 Core, 2GB RAM)

## Performance Improvements Implemented

### 1. **Database Query Optimization**
- ✅ Added query scopes to prevent N+1 queries
- ✅ Models only select necessary columns (reduced memory per query)
- ✅ Eager loading with `withXxx()` scopes
- ✅ Database indexes on frequently filtered columns

**Models optimized:**
- `SalesInvoice` → `onlyListColumns()`, `withCustomerInfo()`, `active()`, `canceled()`, `forSemester()`
- `Product` → `onlyListColumns()`, `withCategoryInfo()`, `active()`, `inCategory()`, `lowStock()`
- `Customer` → `onlyListColumns()`, `withLevel()`, `withOutstanding()`, `inCity()`
- `OutgoingTransaction` → `onlyListColumns()`, `withSupplierInfo()`, `withCreator()`, `forSemester()`

### 2. **Database Indexes**
Run migration to add performance indexes:
```bash
php artisan migrate --path=database/migrations/2026_02_14_000000_add_performance_indexes.php
```

**Indexes added on:**
- `sales_invoices`: customer_id, invoice_date, semester_period, is_canceled, combined indexes
- `products`: item_category_id, is_active, stock
- `customers`: customer_level_id, city
- `outgoing_transactions`: supplier_id, transaction_date, semester_period
- `receivable_ledgers`: customer_id, entry_date (combined)
- `audit_logs`: user_id, created_at

### 3. **Caching Strategy**
- `AppCache` class handles versioned cache keys
- `OptimizedCache` provides high-level caching for expensive computations
- Cache durations: 1 hour (frequent), 1 day (moderate), 1 week (stable)

### 4. **Memory-Efficient Query Processing**
Use `EfficientQueryProcessing` trait for large datasets:
```php
// Process large datasets in chunks without loading all into memory
Model::processInChunks(function ($items) {
    // Process $items
}, 500);

// Or use cursor for lazy loading (export features)
foreach (Model::lazyLoad() as $item) {
    // Process $item
}
```

### 5. **Enums for Type Safety & Memory**
- `InvoicePaymentStatus` enum (replaces string comparisons)
- `UserRole` enum (replaces role string checking)

### 6. **Performance Monitoring**
Use `PerformanceMonitor` to identify bottlenecks:
```php
// Log slow queries (queries > 100ms)
PerformanceMonitor::enableQueryLogging();

// Monitor memory usage
PerformanceMonitor::logMemoryUsage('operation-name');
```

## Recommended VPS Configurations

### PHP Configuration (php.ini)
```ini
memory_limit = 512M           # Per-process limit
max_execution_time = 30       # Timeout for scripts
max_input_time = 30
post_max_size = 20M
upload_max_filesize = 10M
```

### MySQL Configuration (my.cnf)
```ini
[mysqld]
max_connections = 50          # Limited for 2GB RAM
innodb_buffer_pool_size = 512M
innodb_log_file_size = 100M
innodb_flush_log_at_trx_commit = 2  # Balance safety & performance
query_cache_type = 1
query_cache_size = 64M
tmp_table_size = 64M
max_allowed_packet = 64M
```

### Nginx Configuration
```nginx
worker_processes auto;
worker_connections 1024;

# Enable gzip compression
gzip on;
gzip_types text/plain text/css application/json;
gzip_min_length 1000;

# Cache static assets
location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
    expires 30d;
    add_header Cache-Control "public, immutable";
}

# PHP-FPM pooling
upstream php_backend {
    server unix:/run/php-fpm.sock;
}

# Timeouts
proxy_connect_timeout 10s;
proxy_send_timeout 10s;
proxy_read_timeout 10s;
```

### PHP-FPM Configuration
```ini
[www]
pm = ondemand                    # Dynamic processes
pm.max_children = 10            # Max processes on 2GB RAM
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 5
pm.process_idle_timeout = 10s

# Limit memory per process
php_admin_value[memory_limit] = 256M
```

## Deployment Checklist

- [ ] Run database migrations: `php artisan migrate --force`
- [ ] Install database indexes: `php artisan migrate --path=database/migrations/2026_02_14_000000_add_performance_indexes.php`
- [ ] Configure caching: `php artisan cache:clear && php artisan config:cache`
- [ ] Optimize autoloader: `composer install --optimize-autoloader --no-dev`
- [ ] Enable query caching: Update MySQL `query_cache_size` to 64M
- [ ] Set up supervisor for queue workers (if using jobs): `php artisan queue:work`
- [ ] Enable OPcache in PHP for bytecode caching
- [ ] Monitor with Performance Monitor in development

## Optimization Results Expected

**Before:** Typical response time 500-1000ms with 100 concurrent users
**After:** 100-300ms with better memory usage and query efficiency

## Monitoring & Maintenance

### Regular Tasks
1. **Weekly:** Monitor slow query logs
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Monthly:** Analyze database query performance
   ```sql
   SELECT * FROM mysql.slow_log;
   ```

3. **Quarterly:** Review and optimize large data exports using cursor-based loading

## Further Optimizations (If Needed)

1. **Redis Caching** - Replace file cache with Redis for distributed caching
2. **Database Read Replicas** - Add read-only replica for reporting queries
3. **Message Queue** - Use Beanstalk/Redis for async operations
4. **CDN** - Offload static assets to CDN to reduce bandwidth
5. **Database Connection Pooling** - Use PgBouncer or ProxySQL for connection reuse

## Support Commands

```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear

# Monitor processes
ps aux | grep -E 'php|mysql|nginx'

# Check memory usage
free -h

# Monitor disk space
df -h

# Real-time process monitoring
htop
```

---

**Last Updated:** February 14, 2026
**VPS Specs:** 2 Core CPU, 2GB RAM
