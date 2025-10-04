# Stock Minimum API Documentation

## Overview
The Stock Minimum API provides CRUD operations and bulk operations for managing stock minimum records. Each record includes item information with minimum and maximum stock levels, automatically assigned to warehouse ID 2682.

## Base URL
```
http://127.0.0.1:8000/stock-minimum/
```

## Data Model
Each stock minimum record contains:
- `id` - Auto-increment primary key
- `item_id` - Item identifier (string)
- `name` - Item name (string)
- `minimum` - Minimum stock level (number)
- `maximum` - Maximum stock level (number)
- `warehouse_id` - Warehouse identifier (auto-resolved from warehouse_name)
- `warehouse_name` - Warehouse name (used to find warehouse_id)
- `created_at` - Timestamp
- `updated_at` - Timestamp

---

## 1. Individual CRUD Operations

### 1.1 Get All Stock Minimums
**GET** `/stock-minimum/`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "item_id": "ITEM001",
      "name": "Product A",
      "minimum": 10,
      "maximum": 100,
      "warehouse_id": 2682,
      "created_at": "2025-10-04T12:00:00.000000Z",
      "updated_at": "2025-10-04T12:00:00.000000Z"
    }
  ],
  "message": "Stock minimums retrieved successfully"
}
```

### 1.2 Create Stock Minimum
**POST** `/stock-minimum/`

**Request Body:**
```json
{
  "item_id": "ITEM001",
  "name": "Product A",
  "minimum": 10,
  "maximum": 100,
  "warehouse_name": "Main Warehouse"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "item_id": "ITEM001",
    "name": "Product A",
    "minimum": 10,
    "maximum": 100,
    "warehouse_id": 2682,
    "created_at": "2025-10-04T12:00:00.000000Z",
    "updated_at": "2025-10-04T12:00:00.000000Z"
  },
  "message": "Stock minimum created successfully"
}
```

### 1.3 Get Specific Stock Minimum
**GET** `/stock_minimum/{id}`

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "item_id": "ITEM001",
    "name": "Product A",
    "minimum": 10,
    "maximum": 100,
    "warehouse_id": 2682,
    "created_at": "2025-10-04T12:00:00.000000Z",
    "updated_at": "2025-10-04T12:00:00.000000Z"
  },
  "message": "Stock minimum retrieved successfully"
}
```

### 1.4 Update Stock Minimum
**PUT** `/stock-minimum/{id}`

**Request Body (only include fields you want to update):**
```json
{
  "item_id": "ITEM001_UPDATED",
  "name": "Product A Updated",
  "minimum": 15,
  "maximum": 110,
  "warehouse_name": "Main Warehouse"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "item_id": "ITEM001_UPDATED",
    "name": "Product A Updated",
    "minimum": 15,
    "maximum": 110,
    "warehouse_id": 2682,
    "created_at": "2025-10-04T12:00:00.000000Z",
    "updated_at": "2025-10-04T12:01:00.000000Z": "Product A Updated",
    "minimum": 15,
    "maximum": 110
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "item_id": "ITEM001_UPDATED",
    "name": "Product A Updated",
    "minimum": 15,
    "maximum": 110,
    "warehouse_id": 2682,
    "created_at": "2025-10-04T12:00:00.000000Z",
    "updated_at": "2025-10-04T12:01:00.000000Z"
  },
  "message": "Stock minimum updated successfully"
}
```

### 1.5 Delete Stock Minimum
**DELETE** `/stock-minimum/{id}`

**Response:**
```json
{
  "success": true,
  "message": "Stock minimum deleted successfully"
}
```

---

## 2. Bulk Operations

### 2.1 Bulk Insert
**POST** `/stock-minimum/bulk-insert`

**Request Body:**
```json
{
  "data": [
    {
      "item_id": "ITEM001",
      "name": "Product A",
      "minimum": 10,
      "maximum": 100,
      "warehouse_name": "Main Warehouse"
    },
    {
      "item_id": "ITEM002",
      "name": "Product B", 
      "minimum": 5,
      "maximum": 50,
      "warehouse_name": "Secondary Warehouse"
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "inserted": [
      {
        "id": 1,
        "item_id": "ITEM001",
        "name": "Product A",
        "minimum": 10,
        "maximum": 100,
        "warehouse_id": 2682,
        "created_at": "2025-10-04T12:00:00.000000Z",
        "updated_at": "2025-10-04T12:00:00.000000Z"
      }
    ],
    "errors": [],
    "summary": {
      "total": 2,
      "successful": 2,
      "failed": 0
    }
  },
  "message": "Bulk insert completed: 2/2 items inserted successfully"
}
```

### 2.2 Bulk Update
**PUT** `/stock-minimum/bulk-update`

**Request Body:**
```json
{
  "data": [
    {
      "id": 1,
      "item_id": "ITEM001_UPDATED",
      "minimum": 15,
      "maximum": 120,
      "warehouse_id": 2682
    },
    {
      "id": 2,
      "minimum": 8,
      "warehouse_id": 2682
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "updated": [
      {
        "id": 1,
        "item_id": "ITEM001_UPDATED",
        "name": "Product A",
        "minimum": 15,
        "maximum": 120,
        "warehouse_id": 2682,
        "created_at": "2025-10-04T12:00:00.000000Z",
        "updated_at": "2025-10-04T12:01**:00.000000Z"
      }
    ],
    "errors": [],
    "summary": {
      "total": 2,
      "successful": 2,
      "failed": 0
    }
  },
  "message": "Bulk update completed: 2/2 items updated successfully"
}
```

### 2.3 Bulk Upsert (Insert or Update)
**POST** `/stock-minimum/bulk-upsert`

**Request Body:**
```json
{
  "data": [
    {
      "item_id": "ITEM003",
      "name": "Product C",
      "minimum": 20,
      "maximum": 200,
      "warehouse_id": 2682
    },
    {
      "item_id": "ITEM004_UPDATED",
      "name": "Product C",
      "minimum": 25,
      "maximum": 250,
      "warehouse_id": 2682
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "upserted": [
      {
        "id": 3,
        "item_id": "ITEM003",
        "name": "Product C",
        "minimum": 20,
        "maximum": 200,
        "warehouse_id": 2682,
        "created_at": "2025-10-04T12:02:00.000000Z",
        "updated_at": "2025-10-04T12:02:00.000000Z"
      },
      {
        "id": 3,
        "item_id": "ITEM004_UPDATED",
        "name": "Product C",
        "minimum": 25,
        "maximum": 250,
        "warehouse_id": 2682,
        "created_at": "2025-10-04T12:02:00.000000Z",
        "updated_at": "2025-10-04T12:03:00.000000Z"
      }
    ],
    "errors": [],
    "summary": {
      "total": 2,
      "successful": 2,
      "failed": 0
    }
  },
  "message": "Bulk upsert completed: 2/2 items processed successfully"
}
```

---

## 3. Error Responses

### 3.1 Database Connection Error
```json
{
  "success": false,
  "message": "Failed to create stock minimum",
  "error": "SQLSTATE[HY000] [2002] No such file or directory..."
}
```

### 3.2 Invalid ID Error
```json
{
  "success": false,
  "message": "Stock mínimum not found",
  "error": "No query results for model [App\\Models\\StockMinimum] 999"
}
```

### 3.3 Bulk Operation Errors
```json
{
  "success": false,
  "data": {
    "inserted": [...],
    "errors": [
      {
        "index": 1,
        "data": {...},
        "error": "Specific error message"
      }
    ],
    "summary": {
      "total": 2,
      "successful": 1,
      "failed": 1
    }
  },
  "message": "Bulk insert completed: 1/2 items inserted successfully"
}
```

---

## 4. Features & Notes

### ✅ Features
- **No Validation**: Accepts any data format
- **Warehouse Name Lookup**: Uses warehouse_name to find warehouse_id automatically
- **Partial Success**: Bulk operations report individual failures
- **Error Handling**: Comprehensive error messages
- **Flexible Updates**: Send only fields you want to update

### 📝 Notes
- Use `warehouse_name` instead of `warehouse_id` - system automatically finds the ID
- If warehouse_name not found, warehouse_id will be null
- Can still use warehouse_id directly if needed (takes precedence over warehouse_name)
- Missing fields default to `null` or `0`
- Bulk operations return detailed error information
- HTTP 207 status for partial success in bulk operations

### 🔧 Requirements
- MySQL database with `stock_minimums` table
- PHP 8.3+ with Laravel/Lumen framework
- Valid database connection

---

## 5. Testing Examples

### cURL Examples

**Create Single Record:**
```bash
curl -X POST http://127.0.0.1:8000/stock-minimum/ \
  -H "Content-Type: application/json" \
  -d '{"item_id":"TEST001","name":"Test Product","minimum":10,"maximum":100,"warehouse_name":"Main Warehouse"}'
```

**Bulk Insert:**
```bash
curl -X POST http://127.0.0.1:8000/stock-minimum/bulk-insert \
  -H "Content-Type: application/json" \
  -d '{"data":[{"item_id":"ITEM001","name":"Product A","minimum":10,"maximum":100,"warehouse_name":"Main Warehouse"}]}'
```

**Update Record:**
```bash
curl -X PUT http://127.0.0.1:8000/stock-minimum/1 \
  -H "Content-Type: application/json" \
  -d '{"minimum":15,"maximum":120,"warehouse_name":"Main Warehouse"}'
```

---

**Last Updated:** October 4, 2025  
**Version:** 1.0
