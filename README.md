## Установка

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

В `.env` указать:

```
WB_API_HOST=http://...
WB_API_KEY=your_key
```

## Таблицы БД

### stocks — Остатки

| Поле | Тип | Описание |
|------|-----|----------|
| id | bigint PK | Auto-increment |
| nm_id | bigint, index | Артикул WB |
| date | date, index | Дата |
| last_change_date | date | Дата последнего изменения |
| supplier_article | string | Артикул поставщика |
| tech_size | string | Размер |
| barcode | bigint | Штрихкод |
| quantity | int | Количество |
| is_supply | boolean | Поставка |
| is_realization | boolean | Реализация |
| quantity_full | int | Полное количество |
| warehouse_name | string | Склад |
| in_way_to_client | int | В пути к клиенту |
| in_way_from_client | int | В пути от клиента |
| subject | string | Предмет |
| category | string | Категория |
| brand | string | Бренд |
| sc_code | bigint | Код SC |
| price | decimal(10,2) | Цена |
| discount | decimal(10,2) | Скидка |

### incomes — Поставки

| Поле | Тип | Описание |
|------|-----|----------|
| id | bigint PK | Auto-increment |
| income_id | bigint, index | ID поставки |
| number | string | Номер |
| date | date, index | Дата |
| last_change_date | date | Дата последнего изменения |
| supplier_article | string | Артикул поставщика |
| tech_size | string | Размер |
| barcode | bigint | Штрихкод |
| quantity | int | Количество |
| total_price | decimal(10,2) | Сумма |
| date_close | date | Дата закрытия |
| warehouse_name | string | Склад |
| nm_id | bigint, index | Артикул WB |

### sales — Продажи

| Поле | Тип | Описание |
|------|-----|----------|
| sale_id | string PK | ID продажи (строковый ключ) |
| g_number | string, index | Номер |
| date | date, index | Дата |
| last_change_date | date | Дата последнего изменения |
| supplier_article | string | Артикул поставщика |
| tech_size | string | Размер |
| barcode | bigint | Штрихкод |
| total_price | decimal(10,2) | Сумма |
| discount_percent | decimal(5,2) | Процент скидки |
| is_supply | boolean | Поставка |
| is_realization | boolean | Реализация |
| promo_code_discount | int | Скидка по промокоду |
| warehouse_name | string | Склад |
| country_name | string | Страна |
| oblast_okrug_name | string | Область/округ |
| region_name | string | Регион |
| income_id | bigint, index | ID поставки |
| odid | string | ODID |
| spp | int | СПП |
| for_pay | decimal(10,2) | К выплате |
| finished_price | decimal(10,2) | Итоговая цена |
| price_with_disc | decimal(10,2) | Цена со скидкой |
| nm_id | bigint, index | Артикул WB |
| subject | string | Предмет |
| category | string | Категория |
| brand | string | Бренд |
| is_storno | boolean | Сторно |

### orders — Заказы

| Поле | Тип | Описание |
|------|-----|----------|
| id | bigint PK | Auto-increment |
| g_number | string, index | Номер |
| date | datetime, index | Дата и время |
| last_change_date | date | Дата последнего изменения |
| supplier_article | string | Артикул поставщика |
| tech_size | string | Размер |
| barcode | bigint | Штрихкод |
| total_price | decimal(10,2) | Сумма |
| discount_percent | decimal(5,2) | Процент скидки |
| warehouse_name | string | Склад |
| oblast | string | Область |
| income_id | bigint, index | ID поставки |
| odid | string | ODID |
| nm_id | bigint, index | Артикул WB |
| subject | string | Предмет |
| category | string | Категория |
| brand | string | Бренд |
| is_cancel | boolean | Отменён |
| cancel_dt | datetime | Дата отмены |

## Команды

### Загрузка данных

```bash
php artisan db:get-all-data
```

Ставит в очередь джобы для загрузки всех данных с 2020-01-01. Можно указать другую начальную дату:

```bash
php artisan db:get-all-data --from=2023-01-01
```

### Запуск воркера очередей

```bash
php artisan queue:work --timeout=120
```

### Разработка (сервер + воркер + логи + vite)

```bash
npm run dev
```

### Просмотр failed jobs

```bash
php artisan queue:failed
```

### Повторный запуск упавших джоб

```bash
php artisan queue:retry all
```

## Архитектура импорта

Импорт работает через паттерн **Dispatcher + Page Jobs**:

1. `WbDispatcherJob` — забирает первую страницу API, узнаёт общее количество страниц, диспатчит батч из `WbPageJob`
2. `WbPageJob` — одна джоба на одну страницу, работает в рамках `Bus::batch()` с `allowFailures()`

Для stocks, incomes, orders — таблица очищается перед загрузкой (`truncate` + `insert`).
Для sales — данные обновляются через `upsert` по `sale_id`.