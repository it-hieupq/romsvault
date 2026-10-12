# COMMAND

## Migrate DB: romsvault -> romsvault_prod

`romsvault_prod` là bản dữ liệu đã chuẩn hoá, chờ push lên PROD. Collation phải khớp
`romsvault` (`utf8mb4_unicode_ci`). Bước 1 tự bỏ qua nếu `romsvault_prod` chưa tồn tại.

> PROD chạy **MariaDB 10.5.6**, không có họ collation `uca1400_*` (UCA 14.0.0, default của
> MariaDB 11). Bảng nào mang `uca1400` sẽ làm dump chết khi import lên PROD với
> `ERROR 1273 Unknown collation`. Luôn khai collation tường minh khi tạo bảng/database.

```bash
cd /Users/mac/Documents/wp-docker
set -a; source .env; set +a

M() { docker exec -e MYSQL_PWD="$MARIADB_ROOT_PASSWORD" wp_mariadb mariadb -uroot "$@"; }
D() { docker exec -e MYSQL_PWD="$MARIADB_ROOT_PASSWORD" wp_mariadb mariadb-dump -uroot "$@"; }

# 1. Backup romsvault_prod nếu đã tồn tại — chạy TRƯỚC khi drop
if M -N -B -e "select count(*) from information_schema.schemata where schema_name='romsvault_prod';" | grep -q '^1$'; then
  BK="romsvault_prod-$(date +%Y%m%d-%H%M).sql.gz"
  D --single-transaction --quick --default-character-set=utf8mb4 romsvault_prod | gzip -c > "$BK"
  gzip -t "$BK" && ls -lh "$BK"
fi

# 2. Tạo lại romsvault_prod rỗng
M -e "DROP DATABASE IF EXISTS romsvault_prod;
      CREATE DATABASE romsvault_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 3. Đẩy data — pipe chạy trong container nên data không đi qua host
docker exec -e MYSQL_PWD="$MARIADB_ROOT_PASSWORD" wp_mariadb sh -c \
  'mariadb-dump -uroot --single-transaction --quick --default-character-set=utf8mb4 romsvault | mariadb -uroot romsvault_prod'

# 4. Verify: COUNT(*) thật của từng bảng ở 2 DB phải giống nhau
for DB in romsvault romsvault_prod; do
  Q=$(M -N -B -e "set session group_concat_max_len=1000000;
    select group_concat(concat(\"select '\",table_name,\"' t,count(*) c from \`$DB\`.\`\",table_name,\"\`\") separator ' union all ')
    from information_schema.tables where table_schema='$DB' and table_type='BASE TABLE';")
  M -N -B -e "$Q" | sort > "/tmp/rows_$DB.txt"
done
diff /tmp/rows_romsvault.txt /tmp/rows_romsvault_prod.txt \
  && echo "OK: $(wc -l < /tmp/rows_romsvault.txt) bảng, row count khớp 100%"
```

Không cần `FLUSHALL` Redis: không site nào serve `romsvault_prod`.

Dùng `MYSQL_PWD` từ `.env` (`MARIADB_ROOT_PASSWORD`), không truyền `-p` trên command line
để password không lọt vào process list.
