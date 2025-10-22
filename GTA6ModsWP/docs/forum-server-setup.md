# Forum Infrastructure Server Tasks

The custom forum depends on a handful of background workers and caching layers. Configure the following on the production server to guarantee optimal performance and data consistency.

## 1. Persistent Object Cache (Redis)

1. Install and enable the `redis-server` package on the host (or use your managed Redis instance).
2. Install the official WordPress Redis object cache drop-in (for example [`redis-cache`](https://wordpress.org/plugins/redis-cache/)).
3. Enable the drop-in by defining the connection settings in `wp-config.php`:
   ```php
   define( 'WP_REDIS_HOST', '127.0.0.1' );
   define( 'WP_REDIS_PORT', 6379 );
   define( 'WP_CACHE', true );
   ```
4. Flush the cache once after activation to prime the keyspace:
   ```bash
   wp redis enable
   wp redis flush
   ```

> **Why?** Votes, rate-limits, and the dirty-score queues all live in Redis. Without a persistent object cache the real-time scores and asynchronous syncing flow will fall back to slower database writes.

## 2. Real Cron Trigger for WP-Cron

Two WP-Cron events power the asynchronous jobs:

- `gta6_sync_scores_from_redis` (every 5 minutes)
- `gta6_recalculate_hot_scores` (every 15 minutes)

Disable WordPress’s built-in pseudo-cron and wire the jobs to the system scheduler.

1. Edit `wp-config.php` and add:
   ```php
   define( 'DISABLE_WP_CRON', true );
   ```
2. Create a crontab entry for the web user (replace paths as needed):
   ```cron
   */5 * * * * /usr/bin/wp --path=/home/ashley/topiku.hu/public cron event run --due-now >/dev/null 2>&1
   ```

> **Why?** This guarantees that the score synchronisation and hot score calculations run even when the site has no traffic. Adjust the WP-CLI path to match your server.

## 3. Optional: Dedicated Notification Processor

Notifications are enqueued via `wp_schedule_single_event()`. If you prefer to process them outside of WordPress cron, you can map the action to a separate WP-CLI runner:

```cron
* * * * * /usr/bin/wp --path=/home/ashley/topiku.hu/public cron event run --due-now --allow-root >/dev/null 2>&1
```

This is optional if you already execute the global cron entry above every five minutes.

## 4. Monitoring

- Set up Redis keyspace alerts for `gta6_forum_scores:*` and `gta6_forum_sync:dirty-scores` to ensure keys are expiring as expected (they default to 24 hours).
- Add log shipping for the `wp-content/debug.log` file so forum API failures can be spotted quickly. Enable with:
  ```php
  define( 'WP_DEBUG', true );
  define( 'WP_DEBUG_LOG', true );
  define( 'WP_DEBUG_DISPLAY', false );
  ```

Following the steps above keeps the forum queue workers responsive and ensures that the real-time voting experience matches the design goals.
