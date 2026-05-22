# Performance Optimization Checklist

## Pre-Deployment

### Server Configuration
- [ ] Run database optimization script
  ```bash
  php scripts/optimize_database.php
  ```
- [ ] Verify `.htaccess` is in place and Apache mod_rewrite is enabled
- [ ] Enable gzip compression in Apache/Nginx
- [ ] Set proper file permissions (644 for files, 755 for directories)
- [ ] Ensure `.cache/` directory is writable (`chmod 755 .cache`)

### Database
- [ ] All required indexes created
- [ ] Database connection pooling configured (if using)
- [ ] Slow query log enabled for monitoring
- [ ] Regular backups configured

### Frontend Assets
- [ ] Images optimized and compressed
- [ ] WebP format available for modern browsers
- [ ] Fonts properly cached
- [ ] CSS and JS minified in production
- [ ] Lucide icons loading deferred

### Caching
- [ ] Cache directory exists and is writable
- [ ] Cache TTLs appropriate for your data
- [ ] Cache invalidation strategy implemented
- [ ] Monitoring for cache hits vs misses

## Deployment Verification

### Page Load Performance
- [ ] Homepage loads in < 1 second
- [ ] Creator dashboard loads in < 500ms (with warm cache)
- [ ] First Contentful Paint < 1.5 seconds
- [ ] Largest Contentful Paint < 3 seconds
- [ ] Cumulative Layout Shift < 0.1

### Database Performance
- [ ] No N+1 query problems
- [ ] Avg query time < 100ms
- [ ] No queries exceeding 500ms
- [ ] Database indexes being used (EXPLAIN)

### API Performance
- [ ] Notification API responds in < 200ms
- [ ] Batch operations process in < 500ms
- [ ] Pagination working correctly
- [ ] API caching headers set

### Monitoring
- [ ] Server response time tracked
- [ ] Page load metrics collected
- [ ] Error rates monitored
- [ ] Cache hit rate > 80% for frequently accessed data

## Post-Deployment Optimization

### Testing
- [ ] Load test with 100+ concurrent users
- [ ] Monitor CPU and memory usage
- [ ] Check database connection pool
- [ ] Verify cache effectiveness
- [ ] Test on various network speeds (3G, 4G, WiFi)

### Maintenance
- [ ] Monitor slow query log
- [ ] Clear expired cache regularly
- [ ] Review server logs for errors
- [ ] Analyze user behavior metrics

### Continuous Improvement
- [ ] A/B test optimizations
- [ ] Gather user feedback on speed
- [ ] Update cache TTLs based on data volatility
- [ ] Profile and optimize hot paths

## Optimization by Page

### Homepage (index.php)
- [x] Caching implemented
- [x] Lazy loading for featured campaigns
- [x] Lazy loading for contests
- [x] HTTP caching headers
- [ ] Additional: Image CDN for hero section

### Creator Dashboard
- [x] Combined metrics query
- [x] Cache 10 minutes
- [ ] Additional: Pagination for jobs list
- [ ] Additional: Lazy load recommendation section

### Notifications
- [x] API caching (60s for unread count)
- [x] List caching (5 minutes)
- [x] Batch cache invalidation
- [ ] Additional: WebSocket for real-time (future)

### Contest Board
- [ ] Implement pagination
- [ ] Cache by sort type
- [ ] Optimize submission count query
- [ ] Add filters caching

### Campaign Pages
- [ ] Cache individual campaigns
- [ ] Optimize application queries
- [ ] Add submission counting index
- [ ] Implement lazy loading

## Performance Budgets

| Metric | Target | Current |
|--------|--------|---------|
| First Byte (TTFB) | < 500ms | TBD |
| First Contentful Paint | < 1.5s | TBD |
| Time to Interactive | < 3s | TBD |
| Largest Contentful Paint | < 3s | TBD |
| Cumulative Layout Shift | < 0.1 | TBD |
| Total Page Size | < 2MB | TBD |
| Requests | < 50 | TBD |
| Cache Hit Rate | > 80% | TBD |
| Avg Response Time | < 200ms | TBD |

## Tools & Resources

### Performance Testing
- Google Lighthouse (Chrome DevTools)
- WebPageTest.org
- GTmetrix
- Pingdom
- New Relic

### Monitoring
- DataDog
- New Relic
- Scout APM
- MySQL slow query log
- Apache/Nginx access logs

### Optimization Tools
- ImageOptim (images)
- PurifyCss (unused CSS)
- UglifyJS (minify JS)
- html-minifier (minify HTML)
- WOFF2 converter (fonts)

## Common Issues & Solutions

### Issue: Cache not clearing after updates
**Solution**: Implement proper cache invalidation on data changes
```php
CacheManager::delete("key");
```

### Issue: Database queries still slow
**Solution**: Run optimize script and check EXPLAIN output
```sql
EXPLAIN SELECT ...;
```

### Issue: High memory usage
**Solution**: Reduce cache TTLs or implement cache size limits

### Issue: Cache directory permission errors
**Solution**: Ensure .cache/ is writable
```bash
chmod 755 .cache/
```

## Monitoring Dashboard

Create a monitoring page to track:
```php
// Query performance
SELECT COUNT(*), AVG(query_time) FROM slow_log WHERE start_time > DATE_SUB(NOW(), INTERVAL 1 HOUR);

// Cache effectiveness
SELECT hits / (hits + misses) as cache_hit_ratio FROM cache_stats;

// Page load times
SELECT page, AVG(load_time) FROM page_metrics GROUP BY page;
```

## Support

For performance issues:
1. Check PERFORMANCE.md for implementation details
2. Run `php scripts/optimize_database.php`
3. Clear cache and test again
4. Check slow query log
5. Review server resources (CPU, Memory, Disk I/O)
