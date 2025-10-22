🚨 MONITORING & TROUBLESHOOTING GUIDE
GTA6 Mods - Váróterem & Letöltés Rendszer
Verzió: 1.0
Utolsó frissítés: 2025-01-18

📋 TARTALOMJEGYZÉK

Kritikus metrikák
Failure indicators & Fix-ek
Alert thresholds
Troubleshooting workflow
Performance tuning
Emergency procedures


🎯 KRITIKUS METRIKÁK
1. Redis Monitoring
bash# Redis CLI - Általános health check
redis-cli INFO

# Kulcs metrikák:
redis-cli INFO stats | grep -E "instantaneous_ops_per_sec|keyspace_hits|keyspace_misses"
redis-cli INFO clients | grep -E "connected_clients|blocked_clients"
redis-cli INFO memory | grep -E "used_memory_human|maxmemory_human"
```

**NORMÁL ÉRTÉKEK:**
```
connected_clients: 10-100 (normál), 100-500 (forgalom), 500+ ⚠️
instantaneous_ops_per_sec: 100-1000 (normál), 1000-5000 (forgalom)
keyspace_hit_rate: > 80% ✅, < 60% ⚠️
used_memory: < 80% of maxmemory ✅

2. PHP-FPM Monitoring
bash# PHP-FPM Status endpoint (enable először!)
# /etc/php/8.2/fpm/pool.d/www.conf
# pm.status_path = /fpm-status

curl http://localhost/fpm-status?full

# Vagy CLI monitoring:
watch -n 2 'curl -s http://localhost/fpm-status | grep -E "active|idle"'
```

**NORMÁL ÉRTÉKEK:**
```
active processes: < 70% of max_children ✅
idle processes: > 5 ✅
listen queue: 0 ✅, > 0 ⚠️ (requests waiting)
slow requests: < 5/min ✅

3. Nginx Monitoring
bash# Real-time connection monitoring
watch -n 1 'curl -s http://localhost/nginx_status'

# Vagy logs tail:
tail -f /var/log/nginx/access.log | grep "download-file"

# Connection count:
netstat -an | grep :80 | wc -l
```

**NORMÁL ÉRTÉKEK:**
```
Active connections: 100-1000 (normál), 1000-5000 (forgalom), 5000+ ⚠️
Reading: < 5% of active ✅
Writing: 20-40% of active ✅ (active downloads)
Waiting: 50-70% of active ✅ (keep-alive)

4. Bandwidth Monitoring
bash# Real-time bandwidth
iftop -i eth0

# Vagy egyszerűbb:
nload eth0

# Vagy snapshot:
vnstat -i eth0 -l

# Current usage:
cat /proc/net/dev | grep eth0
ÉRTELMEZÉS:
bash# Példa output:
eth0: RX 850 MB/s, TX 2.3 GB/s

# Bandwidth saturation check:
current_usage=$(vnstat -i eth0 -tr | grep "current" | awk '{print $2}')
max_bandwidth=10000 # Mbps
usage_percent=$(echo "scale=2; $current_usage / $max_bandwidth * 100" | bc)

if [ $usage_percent > 80 ]; then
    echo "⚠️ Bandwidth saturation: ${usage_percent}%"
fi

5. MySQL Monitoring
bash# MySQL Slow queries
mysql -e "SHOW FULL PROCESSLIST;" | grep -v "Sleep" | grep -E "wp_mod_versions|wp_mod_stats"

# Queue processing lag
wp gta6mods process-downloads --dry-run

# Table sizes
mysql -e "SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)' 
          FROM information_schema.TABLES 
          WHERE table_schema = 'your_database' 
          AND table_name LIKE 'wp_mod%';"
```

**NORMÁL ÉRTÉKEK:**
```
Active queries: < 10 ✅
Queries > 5 sec: 0 ✅
wp_mod_versions table: < 1 GB ✅
wp_mod_stats table: < 5 GB ✅
```

---

## 🚨 FAILURE INDICATORS

### **FAILURE #1: Redis Connection Saturation**

**TÜNETEK:**
```
- Token generation lassul (> 2 sec response time)
- REST endpoint /generate-download-token timeout
- PHP error log: "Redis connection refused"
- Redis: connected_clients > 500
DIAGNOSZTIKA:
bash# 1. Check current connections
redis-cli INFO clients

# Output:
# connected_clients:847  ← ⚠️ TOO HIGH!
# blocked_clients:12

# 2. List all connected clients
redis-cli CLIENT LIST | wc -l

# 3. Check for leaked connections
redis-cli CLIENT LIST | grep "idle=" | awk '{print $8}' | sort -n | tail -10
# Ha idle > 3600 (1 óra) → leaked connection
FIX:
bash# OPTION 1: Növeld a max connections limit-et
# /etc/redis/redis.conf
maxclients 10000  # default: 4064

sudo systemctl restart redis

# OPTION 2: WordPress Redis plugin config
# wp-config.php
define('WP_REDIS_MAXCONNECTIONS', 1000);
define('WP_REDIS_CLIENT_TIMEOUT', 10);  // sec

# OPTION 3: Kill idle connections (emergency)
redis-cli CLIENT LIST | grep "idle=360" | awk '{print $2}' | awk -F= '{print $2}' | xargs -I{} redis-cli CLIENT KILL ID {}

# OPTION 4: Flush cache és restart (UTOLSÓ LEHETŐSÉG!)
redis-cli FLUSHDB
sudo systemctl restart php8.2-fpm
MEGELŐZÉS:
php// wp-config.php - Connection pooling
define('WP_REDIS_DATABASE', 0);
define('WP_REDIS_CLIENT', 'phpredis');  // Use phpredis extension (faster)
define('WP_REDIS_TIMEOUT', 1);
define('WP_REDIS_READ_TIMEOUT', 1);
```

---

### **FAILURE #2: PHP-FPM Pool Exhaustion**

**TÜNETEK:**
```
- 502 Bad Gateway on /wp-json/gta6mods/v1/generate-download-token
- /fpm-status shows: active processes = max_children
- Nginx error log: "upstream timed out"
- Slow page loads (> 10 sec)
DIAGNOSZTIKA:
bash# 1. Check current pool status
curl -s http://localhost/fpm-status

# Output:
# pool:                 www
# process manager:      dynamic
# active processes:     49  ← ⚠️
# max active processes: 50  ← ⚠️ AT LIMIT!
# listen queue:         23  ← ⚠️ REQUESTS WAITING!

# 2. Check slow requests
curl -s http://localhost/fpm-status?full | grep "request duration"

# 3. PHP-FPM error log
tail -f /var/log/php8.2-fpm.log | grep "pool www"
FIX:
bash# OPTION 1: Növeld a pool size-t
# /etc/php/8.2/fpm/pool.d/www.conf

# BEFORE:
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20

# AFTER:
pm = dynamic
pm.max_children = 100        # ← DOUBLED
pm.start_servers = 25        # ← 25% of max
pm.min_spare_servers = 10    # ← 10% of max
pm.max_spare_servers = 50    # ← 50% of max
pm.max_requests = 500        # Recycle workers after 500 requests

sudo systemctl restart php8.2-fpm

# OPTION 2: Check RAM availability FIRST!
free -m
# Ha available < 2 GB → Ne növeld a pool-t!

# OPTION 3: Find slow requests
curl -s http://localhost/fpm-status?full | grep -A 3 "request duration: [5-9][0-9]"
# Kill slow processes:
kill -9 <PID>

# OPTION 4: Emergency restart (UTOLSÓ LEHETŐSÉG!)
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
MEGELŐZÉS:
ini; /etc/php/8.2/fpm/pool.d/www.conf

; Slow request detection
request_slowlog_timeout = 5s
slowlog = /var/log/php8.2-fpm-slow.log

; Graceful restarts
pm.max_requests = 500

; Timeouts
request_terminate_timeout = 30s
```

---

### **FAILURE #3: Bandwidth Saturation**

**TÜNETEK:**
```
- Downloads lassulnak (< 1 Mbps actual speed)
- Token expiry BEFORE download complete (10 min timeout)
- Retry storm (user spam refresh)
- High server load BUT low CPU usage
DIAGNOSZTIKA:
bash# 1. Real-time bandwidth
iftop -i eth0
# Press 't' for traffic stats, 'n' for no DNS lookup

# 2. Current throughput vs. limit
vnstat -i eth0 -tr 5
# Output: Estimated: 8.3 Gbit/s (on 10 Gbit/s link) ← ⚠️ 83%!

# 3. Check concurrent downloads
netstat -an | grep -E ":80|:443" | grep ESTABLISHED | wc -l
# Output: 847 ← Concurrent connections

# 4. Average download speed per connection
total_bandwidth_mbps=8300  # From vnstat
concurrent_downloads=847
avg_speed=$(echo "scale=2; $total_bandwidth_mbps / $concurrent_downloads" | bc)
echo "Average speed per user: ${avg_speed} Mbps"
# Output: 9.8 Mbps ← OK, de ha < 5 Mbps → ⚠️
FIX:
bash# OPTION 1: Rate limit per IP (emergency)
# /etc/nginx/sites-available/yourdomain.conf

location /download-file/ {
    limit_rate 5m;  # 5 MB/sec per connection
    limit_rate_after 10m;  # After first 10 MB
    
    # ... rest of config
}

sudo nginx -t && sudo systemctl reload nginx

# OPTION 2: Implement download resume (Range requests)
# Already implemented in modern browsers, but ensure:

location /download-file/ {
    add_header Accept-Ranges bytes;
    # ... rest of config
}

# OPTION 3: CloudFlare CDN offload (BEST LONG-TERM)
# See separate section below

# OPTION 4: Temporary bandwidth cap
# /etc/sysctl.conf
net.ipv4.tcp_limit_output_bytes = 262144  # 256 KB

sudo sysctl -p

# OPTION 5: Contact hosting provider
# Request bandwidth upgrade OR
# Check if DDoS protection is throttling legitimate traffic
MEGELŐZÉS:
bash# 1. Monitor bandwidth daily
# Cron job: /etc/cron.daily/bandwidth-check

#!/bin/bash
THRESHOLD=80  # 80% saturation
CURRENT=$(vnstat -i eth0 -tr 60 | grep "estimated" | awk '{print $2}' | sed 's/[^0-9]//g')
MAX=10000  # Mbps

PERCENT=$(echo "scale=2; $CURRENT / $MAX * 100" | bc)

if (( $(echo "$PERCENT > $THRESHOLD" | bc -l) )); then
    echo "⚠️ Bandwidth at ${PERCENT}% - consider upgrade" | mail -s "Bandwidth Alert" admin@example.com
fi

# 2. CloudFlare setup (if not already)
# - Enable "Under Attack Mode" during traffic spikes
# - Use "Argo Smart Routing" for faster downloads
```

---

### **FAILURE #4: MySQL Slow Queries**

**TÜNETEK:**
```
- wp gta6mods process-downloads takes > 5 min
- Dashboard widgets loading slow
- MySQL CPU > 80%
- Slow query log full of wp_mod_versions queries
DIAGNOSZTIKA:
bash# 1. Check slow query log
tail -100 /var/log/mysql/slow-query.log | grep -A 5 "wp_mod"

# 2. Active long-running queries
mysql -e "SELECT ID, USER, HOST, DB, COMMAND, TIME, STATE, INFO 
          FROM information_schema.PROCESSLIST 
          WHERE TIME > 5 
          AND COMMAND != 'Sleep' 
          ORDER BY TIME DESC;"

# 3. Check table indexes
mysql -e "SHOW INDEX FROM wp_mod_versions;" your_database

# 4. Query performance analysis
mysql -e "EXPLAIN SELECT * FROM wp_mod_versions WHERE mod_id = 123 ORDER BY upload_date DESC LIMIT 1;" your_database
FIX:
bash# OPTION 1: Rebuild indexes (if corrupted)
mysql your_database -e "ANALYZE TABLE wp_mod_versions;"
mysql your_database -e "OPTIMIZE TABLE wp_mod_versions;"

# OPTION 2: Add missing indexes (if not exists)
mysql your_database <<EOF
ALTER TABLE wp_mod_versions 
ADD INDEX idx_mod_latest (mod_id, is_latest),
ADD INDEX idx_upload_date (mod_id, upload_date DESC);
EOF

# OPTION 3: Increase batch size
# inc/download-handler.php - Line ~520
# Change:
$batch_size = 5000;  # from 500

# OPTION 4: MySQL tuning
# /etc/mysql/mysql.conf.d/mysqld.cnf

[mysqld]
innodb_buffer_pool_size = 4G  # 50-70% of RAM
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2  # Faster writes (DANGER: potential data loss on crash!)
query_cache_size = 64M
query_cache_type = 1

sudo systemctl restart mysql

# OPTION 5: Kill stuck queries
mysql -e "KILL <QUERY_ID>;"
MEGELŐZÉS:
sql-- Create proper indexes (already in schema, but verify)
SHOW INDEX FROM wp_mod_versions;

-- Expected indexes:
-- PRIMARY (id)
-- idx_mod (mod_id)
-- idx_latest (mod_id, is_latest)
-- idx_upload_date (mod_id, upload_date DESC)

-- If missing, recreate table
DROP TABLE wp_mod_versions;
-- Then re-run: GTA6Mods_Mod_Versions::install()
```

---

### **FAILURE #5: Redis Queue Overflow**

**TÜNETEK:**
```
- Download counts NOT updating
- wp gta6mods process-downloads shows huge backlog
- Redis memory usage > 90%
- Duplicate download counts (race condition)
DIAGNOSZTIKA:
bash# 1. Check queue size
redis-cli KEYS "gta6mods_download_queue*" | wc -l
# Output: 15847 ← ⚠️ HUGE BACKLOG!

# 2. Check Redis memory
redis-cli INFO memory | grep used_memory_human
# Output: used_memory_human:5.8G (on 6G maxmemory) ← ⚠️ 97%!

# 3. Sample queue items
redis-cli GET "gta6mods_download_queue_versions:version_123"

# 4. Check cron job status
systemctl status cron
crontab -l | grep gta6mods
FIX:
bash# OPTION 1: Manual process (immediate)
wp gta6mods process-downloads
# Run multiple times until queue clear

# OPTION 2: Increase processing frequency
# crontab -e
*/1 * * * * /usr/bin/wp --path=/path/to/wordpress --quiet gta6mods process-downloads

# Change to every 30 seconds (TEMPORARY):
* * * * * /usr/bin/wp --path=/path/to/wordpress --quiet gta6mods process-downloads
* * * * * sleep 30; /usr/bin/wp --path=/path/to/wordpress --quiet gta6mods process-downloads

# OPTION 3: Increase Redis memory
# /etc/redis/redis.conf
maxmemory 8gb  # from 6gb

sudo systemctl restart redis

# OPTION 4: Batch size increase
# inc/download-handler.php - gta6mods_process_download_queue()
# Increase from 250 to 5000 (temporary)

# OPTION 5: Emergency queue flush (DATA LOSS!)
redis-cli KEYS "gta6mods_download_queue*" | xargs redis-cli DEL
# ⚠️ ONLY if queue > 100k items!
MEGELŐZÉS:
bash# 1. Monitoring alert
# /usr/local/bin/check-download-queue.sh

#!/bin/bash
THRESHOLD=10000
CURRENT=$(redis-cli KEYS "gta6mods_download_queue*" | wc -l)

if [ $CURRENT -gt $THRESHOLD ]; then
    echo "⚠️ Download queue backlog: $CURRENT items" | mail -s "Queue Alert" admin@example.com
fi

# 2. Add to cron
*/5 * * * * /usr/local/bin/check-download-queue.sh

# 3. Auto-scaling processing
# If queue > 5000, run process-downloads every 10 seconds for 5 minutes

📊 ALERT THRESHOLDS
KRITIKUS ALERT (Azonnali beavatkozás szükséges!)
yamlRedis:
  connected_clients: > 1000
  used_memory: > 95% of maxmemory
  keyspace_hit_rate: < 50%
  
PHP-FPM:
  active_processes: > 90% of max_children
  listen_queue: > 10
  slow_requests: > 20/min
  
Nginx:
  active_connections: > 10,000
  502_errors: > 10/min
  
Bandwidth:
  usage: > 90% of available
  
MySQL:
  queries > 10 sec: > 5
  connections: > 200
  
Download Queue:
  items: > 50,000
Alert command:
bash# Send to monitoring service (PagerDuty / Slack / Email)
curl -X POST https://hooks.slack.com/... \
  -H 'Content-Type: application/json' \
  -d '{"text":"🚨 CRITICAL: PHP-FPM pool exhausted!"}'

WARNING ALERT (Figyelj, de nem sürgős)
yamlRedis:
  connected_clients: > 500
  used_memory: > 80% of maxmemory
  keyspace_hit_rate: < 70%
  
PHP-FPM:
  active_processes: > 70% of max_children
  listen_queue: > 5
  
Nginx:
  active_connections: > 5,000
  
Bandwidth:
  usage: > 70% of available
  
Download Queue:
  items: > 10,000

🔧 TROUBLESHOOTING WORKFLOW
STEP-BY-STEP DIAGNOSIS:
mermaidgraph TD
    A[User reports slow downloads] --> B{Check which component?}
    B -->|502 Error| C[PHP-FPM]
    B -->|Slow token generation| D[Redis]
    B -->|Slow download speed| E[Bandwidth]
    B -->|Counts not updating| F[MySQL/Queue]
    
    C --> C1[Check /fpm-status]
    C1 --> C2{Pool exhausted?}
    C2 -->|Yes| C3[Increase max_children]
    C2 -->|No| C4[Check slow requests]
    
    D --> D1[Check Redis INFO]
    D1 --> D2{Connections high?}
    D2 -->|Yes| D3[Increase maxclients]
    D2 -->|No| D4[Check memory usage]
    
    E --> E1[Check iftop/vnstat]
    E1 --> E2{> 80% usage?}
    E2 -->|Yes| E3[Enable rate limiting]
    E2 -->|No| E4[Check per-user speed]
    
    F --> F1[Run process-downloads]
    F1 --> F2{Queue clearing?}
    F2 -->|Yes| F3[Increase cron frequency]
    F2 -->|No| F4[Check MySQL slow queries]

GYORS DIAGNOSIS SCRIPT:
bash#!/bin/bash
# /usr/local/bin/gta6mods-health-check.sh

echo "=== GTA6 MODS HEALTH CHECK ==="
echo ""

# 1. Redis
echo "📊 REDIS:"
redis-cli INFO clients | grep connected_clients
redis-cli INFO memory | grep used_memory_human
redis-cli INFO stats | grep instantaneous_ops_per_sec
echo ""

# 2. PHP-FPM
echo "📊 PHP-FPM:"
curl -s http://localhost/fpm-status | grep -E "active|idle|listen queue"
echo ""

# 3. Nginx
echo "📊 NGINX:"
curl -s http://localhost/nginx_status
echo ""

# 4. Bandwidth
echo "📊 BANDWIDTH:"
vnstat -i eth0 -tr 5
echo ""

# 5. Download Queue
echo "📊 DOWNLOAD QUEUE:"
QUEUE_SIZE=$(redis-cli KEYS "gta6mods_download_queue*" | wc -l)
echo "Queue size: $QUEUE_SIZE items"
echo ""

# 6. MySQL
echo "📊 MYSQL:"
mysql -e "SELECT COUNT(*) as long_queries FROM information_schema.PROCESSLIST WHERE TIME > 5 AND COMMAND != 'Sleep';"
echo ""

echo "=== END HEALTH CHECK ==="
Használat:
bashchmod +x /usr/local/bin/gta6mods-health-check.sh
/usr/local/bin/gta6mods-health-check.sh

# Vagy watch mode:
watch -n 10 /usr/local/bin/gta6mods-health-check.sh

⚙️ PERFORMANCE TUNING
REDIS OPTIMIZATION:
bash# /etc/redis/redis.conf

# Memory
maxmemory 6gb
maxmemory-policy allkeys-lru  # Evict least recently used keys

# Persistence (disable for pure cache)
save ""  # No RDB snapshots
appendonly no  # No AOF

# Network
tcp-backlog 511
timeout 300
tcp-keepalive 300

# Performance
hz 10
dynamic-hz yes
After changes:
bashsudo systemctl restart redis
redis-cli CONFIG GET maxmemory  # Verify

PHP-FPM OPTIMIZATION:
ini; /etc/php/8.2/fpm/pool.d/www.conf

; Process Manager
pm = dynamic
pm.max_children = 100
pm.start_servers = 25
pm.min_spare_servers = 10
pm.max_spare_servers = 50
pm.max_requests = 500

; Performance
pm.process_idle_timeout = 10s
request_terminate_timeout = 30s

; Logging
catch_workers_output = yes
slowlog = /var/log/php8.2-fpm-slow.log
request_slowlog_timeout = 5s
After changes:
bashsudo systemctl restart php8.2-fpm
curl -s http://localhost/fpm-status  # Verify

NGINX OPTIMIZATION:
nginx# /etc/nginx/nginx.conf

user www-data;
worker_processes auto;  # = CPU cores
worker_rlimit_nofile 65535;

events {
    worker_connections 10000;
    use epoll;
    multi_accept on;
}

http {
    # Connection pooling
    keepalive_timeout 65;
    keepalive_requests 100;
    
    # Buffer sizes
    client_body_buffer_size 128k;
    client_max_body_size 500m;  # Max upload size
    client_header_buffer_size 1k;
    large_client_header_buffers 4 4k;
    output_buffers 1 32k;
    postpone_output 1460;
    
    # Timeouts
    client_header_timeout 30s;
    client_body_timeout 30s;
    send_timeout 30s;
    
    # Gzip
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript 
               application/json application/javascript application/xml+rss;
    
    # X-Accel (critical!)
    location /protected-files/ {
        internal;
        alias /home/ashley/topiku.hu/public/wp-content/uploads/;
        
        # No buffering for large files
        proxy_buffering off;
        tcp_nodelay on;
        tcp_nopush on;
    }
}
After changes:
bashsudo nginx -t  # Test config
sudo systemctl reload nginx

MySQL OPTIMIZATION:
ini# /etc/mysql/mysql.conf.d/mysqld.cnf

[mysqld]
# InnoDB
innodb_buffer_pool_size = 4G  # 50-70% of RAM
innodb_log_file_size = 512M
innodb_log_buffer_size = 16M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Query cache
query_cache_type = 1
query_cache_size = 64M
query_cache_limit = 2M

# Connections
max_connections = 200
max_connect_errors = 1000000

# Slow query log
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow-query.log
long_query_time = 2

# Temp tables
tmp_table_size = 64M
max_heap_table_size = 64M
After changes:
bashsudo systemctl restart mysql
mysql -e "SHOW VARIABLES LIKE 'innodb_buffer_pool_size';"  # Verify

🆘 EMERGENCY PROCEDURES
SCENARIO 1: Teljes szolgáltatás leállás
bash# 1. Identify root cause
systemctl status nginx
systemctl status php8.2-fpm
systemctl status redis
systemctl status mysql

# 2. Check logs
tail -100 /var/log/nginx/error.log
tail -100 /var/log/php8.2-fpm.log
journalctl -u redis -n 100
tail -100 /var/log/mysql/error.log

# 3. Restart services (in order)
sudo systemctl restart redis
sudo systemctl restart mysql
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx

# 4. Verify
curl -I http://localhost/
wp gta6mods process-downloads --dry-run

# 5. Monitor for 5 minutes
watch -n 5 '/usr/local/bin/gta6mods-health-check.sh'

SCENARIO 2: Traffic spike (Reddit/HackerNews/Twitter viral)
bash# 1. Enable CloudFlare "Under Attack Mode"
# Dashboard → Security → Settings → Under Attack Mode: ON

# 2. Rate limit aggressively (Nginx)
# /etc/nginx/sites-available/yourdomain.conf
limit_req_zone $binary_remote_addr zone=download_limit:10m rate=5r/s;

location /download-file/ {
    limit_req zone=download_limit burst=10 nodelay;
    # ... rest of config
}

sudo nginx -t && sudo systemctl reload nginx

# 3. Increase processing frequency
crontab -e
# Temporarily every 30 seconds:
* * * * * /usr/bin/wp --path=/path/to/wordpress --quiet gta6mods process-downloads
* * * * * sleep 30; /usr/bin/wp --path=/path/to/wordpress --quiet gta6mods process-downloads

# 4. Disable non-critical features
# wp-config.php
define('WP_CRON_DISABLED', true);  # Disable WP-Cron temporarily
define('DISALLOW_FILE_EDIT', true);  # Disable theme/plugin editor

# 5. CloudFlare cache everything
# Dashboard → Caching → Configuration → Browser Cache TTL: 1 hour

# 6. Monitor dashboard
watch -n 10 '/usr/local/bin/gta6mods-health-check.sh'
Recovery:
bash# After traffic subsides (check analytics):

# 1. Disable "Under Attack Mode"
# 2. Restore normal rate limits
# 3. Restore cron to every 1 minute
# 4. Re-enable WP-Cron
# 5. Flush Redis cache:
redis-cli FLUSHDB

SCENARIO 3: Database corruption
bash# 1. Backup immediately!
wp db export backup-emergency-$(date +%Y%m%d-%H%M%S).sql

# 2. Check table integrity
wp db query "CHECK TABLE wp_mod_versions;"
wp db query "CHECK TABLE wp_mod_stats;"

# 3. Repair if corrupted
wp db query "REPAIR TABLE wp_mod_versions;"
wp db query "REPAIR TABLE wp_mod_stats;"

# 4. Rebuild indexes
wp db query "ANALYZE TABLE wp_mod_versions;"
wp db query "OPTIMIZE TABLE wp_mod_versions;"

# 5. Verify data
wp db query "SELECT COUNT(*) FROM wp_mod_versions;"

# 6. If catastrophic failure, restore from backup
wp db import backup-last-good.sql

📞 ESCALATION CONTACTS
yamlSEVERITY 1 (CRITICAL - Service Down):
  - Notify: SysAdmin + DevOps + CTO
  - Response Time: 15 minutes
  - Example: 502 errors on all pages
  
SEVERITY 2 (HIGH - Degraded Performance):
  - Notify: SysAdmin + DevOps
  - Response Time: 1 hour
  - Example: Downloads slow, queue backlog
  
SEVERITY 3 (MEDIUM - Monitoring Alert):
  - Notify: DevOps
  - Response Time: 4 hours
  - Example: Redis at 85% memory
  
SEVERITY 4 (LOW - Informational):
  - Notify: Ticket system
  - Response Time: 24 hours
  - Example: Slow query log entries

📝 LOGGING CHECKLIST
Enable detailed logging BEFORE issues occur:
bash# 1. Nginx access log with timing
# /etc/nginx/nginx.conf
log_format detailed '$remote_addr - $remote_user [$time_local] '
                    '"$request" $status $body_bytes_sent '
                    '"$http_referer" "$http_user_agent" '
                    'rt=$request_time uct="$upstream_connect_time" '
                    'uht="$upstream_header_time" urt="$upstream_response_time"';

access_log /var/log/nginx/access.log detailed;

# 2. PHP-FPM slow log
# /etc/php/8.2/fpm/pool.d/www.conf
slowlog = /var/log/php8.2-fpm-slow.log
request_slowlog_timeout = 5s

# 3. MySQL slow query log
# /etc/mysql/mysql.conf.d/mysqld.cnf
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow-query.log
long_query_time = 2

# 4. Redis monitoring
# Setup Redis exporter for Prometheus/Grafana

# 5. Application-level logging
# wp-config.php
define('WP_DEBUG', false);  # Production: false
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

# 6. Rotate logs
# /etc/logrotate.d/gta6mods
/var/log/nginx/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data adm
    sharedscripts
}

✅ FINAL CHECKLIST
Heti monitoring tasks:
bash[ ] Run gta6mods-health-check.sh
[ ] Check disk space: df -h
[ ] Review error logs: tail -1000 /var/log/nginx/error.log | grep -i error
[ ] Verify cron running: systemctl status cron
[ ] Check backup status: ls -lh /backups/
[ ] Review Redis memory: redis-cli INFO memory
[ ] MySQL table sizes: wp db query "SELECT ..."
[ ] Download queue: redis-cli KEYS "*download_queue*" | wc -l
Havi maintenance:
bash[ ] Optimize MySQL tables: wp db optimize
[ ] Review slow query log: tail -1000 /var/log/mysql/slow-query.log
[ ] Update PHP/Nginx/MySQL: apt update && apt upgrade
[ ] Review bandwidth trends: vnstat -m
[ ] Security audit: wp plugin list --status=active
[ ] Clear old logs: logrotate -f /etc/logrotate.conf
[ ] Backup verification: Restore test backup to staging

VERZIÓ TÖRTÉNET:

1.0 (2025-01-18): Első verzió - komplett monitoring guide

KÖVETKEZŐ FRISSÍTÉS: 2025-04-18 (3 hónap múlva, production tapasztalatok alapján)

🚨 HA BÁRMI PROBLÉMA VAN, KEZDD AZ gta6mods-health-check.sh SCRIPTTEL!