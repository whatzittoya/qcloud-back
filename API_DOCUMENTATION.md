# Stock Management API Documentation

This document provides comprehensive documentation for all the stock management APIs built for the Quinos Cloud integration.

## Table of Contents

1. [Warehouse Management APIs](#warehouse-management-apis)
2. [Stock Level Management APIs](#stock-level-management-apis)
3. [Stock Movement APIs](#stock-movement-apis)
4. [Data Display APIs](#data-display-apis)

---

## Warehouse Management APIs

### 1. Get Warehouse List
**Endpoint:** `GET /warehouse/list`

**Description:** Retrieves all warehouses for the current client from the database.

**Response Format:**
```json
[
    {
        "id": "2682",
        "name": "LEMBONGAN"
    },
    {
        "id": "2683", 
        "name": "WAREHOUSE_2"
    }
]
```

### 2. Sync Warehouses from Quinos Cloud
**Endpoint:** `GET /stock-level/sync-warehouses`

**Description:** Fetches warehouse data from Quinos Cloud API and updates the local database.

**Response Format:**
```json
{
    "success": true,
    "message": "Warehouses synced successfully. Created: 5, Updated: 3",
    "data": {
        "created": 5,
        "updated": 3,
        "total": 8
    }
}
```

---

## Stock Level Management APIs

### 3. Sync Stock Levels
**Endpoint:** `GET /stock-level/sync/{warehouse_id}`

**Description:** Syncs stock level data from Quinos Cloud API to the `stock_minimum` table.

**Parameters:**
- `warehouse_id`: Warehouse ID or "all" for all warehouses

**Usage Examples:**
- `GET /stock-level/sync/2682` - Sync specific warehouse
- `GET /stock-level/sync/all` - Sync all warehouses

**Response Format:**
```json
{
    "success": true,
    "message": "Stock levels synced successfully. Created: 25, Updated: 15",
    "data": {
        "created": 25,
        "updated": 15,
        "total": 40
    }
}
```

**For "all" warehouses:**
```json
{
    "success": true,
    "message": "All stock levels synced successfully. Total Created: 75, Total Updated: 45",
    "data": {
        "total_created": 75,
        "total_updated": 45,
        "warehouses_processed": 3,
        "warehouse_results": [
            {
                "warehouse_id": "2682",
                "warehouse_name": "LEMBONGAN",
                "status": "success",
                "created": 25,
                "updated": 15,
                "total": 40
            }
        ]
    }
}
```

### 4. Display Stock Minimum Data
**Endpoint:** `GET /stock-level/display`

**Description:** Displays stock minimum data grouped by item ID across all warehouses.

**Response Format:**
```json
{
    "success": true,
    "data": [
        {
            "name": "Aira Jumpsuit Black L",
            "item_id": "759227",
            "LEMBONGAN": {
                "minimum": 1,
                "maximum": 0
            },
            "WAREHOUSE_2": {
                "minimum": 5,
                "maximum": 10
            }
        }
    ],
    "warehouses": [
        {
            "id": "2682",
            "name": "LEMBONGAN"
        }
    ],
    "total_items": 1
}
```

---

## Stock Movement APIs

### 5. Sync Stock Movement (All Warehouses)
**Endpoint:** `GET /stock-level/sync-movement`

**Description:** Syncs stock movement data for all warehouses, automatically detecting gaps and collecting missing data from August 1, 2025 onwards.

**Response Format:**
```json
{
    "success": true,
    "message": "Stock movement synced successfully. Total Created: 150, Total Updated: 25",
    "data": {
        "total_created": 150,
        "total_updated": 25,
        "warehouses_processed": 3,
        "warehouse_results": [
            {
                "warehouse_id": "2682",
                "warehouse_name": "LEMBONGAN",
                "status": "updated",
                "message": "Data updated from 2025-09-10",
                "created": 25,
                "updated": 0,
                "dates_processed": 5,
                "date_range": "2025-09-10 to 2025-09-15"
            }
        ]
    }
}
```

### 6. Sync Stock Movement for Specific Date
**Endpoint:** `GET /stock-level/sync-movement-date/{date}`

**Description:** Syncs stock movement data for a specific date across all warehouses.

**Parameters:**
- `date`: Date in YYYY-MM-DD format

**Usage Example:**
- `GET /stock-level/sync-movement-date/2025-09-15`

**Response Format:**
```json
{
    "success": true,
    "message": "Stock movement synced for date 2025-09-15. Total Created: 50, Total Updated: 10",
    "data": {
        "date": "2025-09-15",
        "total_created": 50,
        "total_updated": 10,
        "warehouses_processed": 3,
        "warehouse_results": [
            {
                "warehouse_id": "2682",
                "warehouse_name": "LEMBONGAN",
                "status": "success",
                "created": 20,
                "updated": 5,
                "total": 25
            }
        ]
    }
}
```

### 7. Sync Stock Movement for Date Range
**Endpoint:** `GET /stock-level/sync-movement-range/{start_date}/{end_date}`

**Description:** Syncs stock movement data for a specific date range across all warehouses.

**Parameters:**
- `start_date`: Start date in YYYY-MM-DD format
- `end_date`: End date in YYYY-MM-DD format

**Usage Example:**
- `GET /stock-level/sync-movement-range/2025-08-01/2025-08-31`

**Response Format:**
```json
{
    "success": true,
    "message": "Stock movement synced for date range 2025-08-01 to 2025-08-31. Total Created: 1500, Total Updated: 250",
    "data": {
        "date_range": {
            "start_date": "2025-08-01",
            "end_date": "2025-08-31"
        },
        "total_created": 1500,
        "total_updated": 250,
        "warehouses_processed": 3,
        "warehouse_results": [
            {
                "warehouse_id": "2682",
                "warehouse_name": "LEMBONGAN",
                "status": "completed",
                "created": 500,
                "updated": 100,
                "dates_processed": 25,
                "dates_skipped": 6,
                "dates_failed": 0,
                "date_range": "2025-08-01 to 2025-08-31"
            }
        ],
        "date_results": [
            {
                "date": "2025-08-01",
                "warehouse_id": "2682",
                "warehouse_name": "LEMBONGAN",
                "status": "success",
                "created": 20,
                "updated": 0,
                "total": 20
            }
        ]
    }
}
```

### 8. Get Available Movement Dates
**Endpoint:** `GET /stock-level/available-dates/{warehouse_id?}`

**Description:** Retrieves all available stock movement dates from the database.

**Parameters:**
- `warehouse_id` (optional): Specific warehouse ID or "all" for all warehouses

**Usage Examples:**
- `GET /stock-level/available-dates` - All warehouses
- `GET /stock-level/available-dates/2682` - Specific warehouse

**Response Format:**
```json
{
    "success": true,
    "data": {
        "available_dates": [
            "2025-09-15",
            "2025-09-14",
            "2025-09-13",
            "2025-08-01"
        ],
        "date_range": {
            "earliest_date": "2025-08-01",
            "latest_date": "2025-09-15",
            "total_dates": 4
        },
        "warehouse_date_info": [
            {
                "warehouse_id": "2682",
                "warehouse_name": "LEMBONGAN",
                "earliest_date": "2025-08-01",
                "latest_date": "2025-09-15",
                "total_dates": 4,
                "date_range": "2025-08-01 to 2025-09-15"
            }
        ]
    }
}
```

### 9. Get Latest Stock Movement Date
**Endpoint:** `GET /stock-level/latest-date/{warehouse_id?}`

**Description:** Gets the latest stock movement date from the database.

**Parameters:**
- `warehouse_id` (optional): Specific warehouse ID or "all" for all warehouses

**Response Format:**
```json
{
    "success": true,
    "data": {
        "latest_date": "2025-09-15",
        "warehouse_id": "2682",
        "warehouse_name": "LEMBONGAN",
        "total_records": 800,
        "warehouse_dates": [
            {
                "warehouse_id": "2682",
                "warehouse_name": "LEMBONGAN",
                "latest_date": "2025-09-15",
                "total_records": 800
            }
        ]
    }
}
```

---

## Data Display APIs

### 10. Get Stock Movement Data
**Endpoint:** `GET /warehouse/stock-movement/{date}`

**Description:** Retrieves stock movement data for a specific date, grouped by item with warehouse columns. Automatically syncs data if not available.

**Parameters:**
- `date`: Date in YYYY-MM-DD format

**Usage Example:**
- `GET /warehouse/stock-movement/2025-09-15`

**Response Format:**
```json
{
    "success": true,
    "data": [
        {
            "code": "ITEM001",
            "name": "Aira Jumpsuit Black L",
            "category": "Jumpsuit CAHAYA PUTIH",
            "LEMBONGAN": 15,
            "LEMBONGAN-requested": 10,
            "WAREHOUSE_2": 8,
            "WAREHOUSE_2-requested": 3,
            "WAREHOUSE_3": 0,
            "WAREHOUSE_3-requested": 0
        }
    ],
    "date": "2025-09-15",
    "warehouses": [
        {
            "id": "2682",
            "name": "LEMBONGAN"
        }
    ],
    "total_records": 1
}
```

**Data Structure Explanation:**
- `{warehouse_name}`: Current listed stock level
- `{warehouse_name}-requested`: Stock needed above minimum (listed - minimum)

---

## Database Tables

### 1. Warehouses Table
```sql
CREATE TABLE warehouses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    warehouse_id VARCHAR(255) UNIQUE NOT NULL,
    client_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 2. Stock Minimum Table
```sql
CREATE TABLE stock_minimum (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    warehouse_id VARCHAR(255) NOT NULL,
    minimum INT NOT NULL,
    maximum INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 3. Stock Movement Table
```sql
CREATE TABLE stock_movement (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    item_code VARCHAR(255) NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    item_category VARCHAR(255) NOT NULL,
    warehouse_id VARCHAR(255) NOT NULL,
    opening INT DEFAULT 0,
    sales INT DEFAULT 0,
    received INT DEFAULT 0,
    released INT DEFAULT 0,
    transfer_in INT DEFAULT 0,
    transfer_out INT DEFAULT 0,
    waste INT DEFAULT 0,
    production INT DEFAULT 0,
    calculated INT DEFAULT 0,
    onhand INT DEFAULT 0,
    listed INT DEFAULT 0,
    stock_movement_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## Error Responses

All APIs return consistent error responses:

```json
{
    "success": false,
    "message": "Error description"
}
```

Common HTTP status codes:
- `200`: Success
- `400`: Bad Request (invalid parameters)
- `404`: Not Found (no data available)
- `500`: Internal Server Error

---

## Authentication

All APIs require authentication. The system uses the current authenticated user's client ID to filter data appropriately.

---

## Notes

1. **Data Sync Strategy**: The system intelligently syncs data only when needed, avoiding duplicate work.
2. **Gap Detection**: Automatic detection of missing data and incremental sync capabilities.
3. **Cross-Warehouse Analysis**: APIs support comparing data across multiple warehouses.
4. **Stock Level Integration**: Stock movement data includes minimum stock level calculations for replenishment planning.
5. **Date Management**: Comprehensive date range support for historical data analysis. 