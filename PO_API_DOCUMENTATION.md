# PO Management API Documentation

## Overview

The PO (Purchase Order) management system allows users to add, update, and delete PO records for stock movements. PO data is stored separately from stock movement data and is linked by item_id and date.

## Database Schema

### Stock Movement PO Table
```sql
CREATE TABLE stock_movement_po (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    date DATE NOT NULL,
    po VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## API Endpoints

### 1. Create PO Record
**POST** `/warehouse/po`

**Request Body:**
```json
{
    "item_id": 123,
    "date": "2024-01-15",
    "po": "PO-2024-001"
}
```

**Response:**
```json
{
    "success": true,
    "message": "PO created successfully",
    "data": {
        "id": 1,
        "item_id": 123,
        "date": "2024-01-15",
        "po": "PO-2024-001",
        "created_at": "2024-01-15T10:00:00.000000Z",
        "updated_at": "2024-01-15T10:00:00.000000Z"
    }
}
```

### 2. Add/Update PO Record (Recommended)
**POST** `/warehouse/po/add`

This endpoint automatically handles both creating new PO records and updating existing ones.

**Request Body:**
```json
{
    "item_id": 123,
    "date": "2024-01-15",
    "po": "PO-2024-001"
}
```

**Response (New Record Created):**
```json
{
    "success": true,
    "message": "PO added successfully",
    "data": {
        "id": 1,
        "item_id": 123,
        "date": "2024-01-15",
        "po": "PO-2024-001",
        "created_at": "2024-01-15T10:00:00.000000Z",
        "updated_at": "2024-01-15T10:00:00.000000Z"
    },
    "action": "created"
}
```

**Response (Existing Record Updated):**
```json
{
    "success": true,
    "message": "PO updated successfully",
    "data": {
        "id": 1,
        "item_id": 123,
        "date": "2024-01-15",
        "po": "PO-2024-001-UPDATED",
        "created_at": "2024-01-15T10:00:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    },
    "action": "updated"
}
```

### 3. Update PO Record
**PUT** `/warehouse/po/{id}`

**Request Body:**
```json
{
    "po": "PO-2024-001-UPDATED"
}
```

**Response:**
```json
{
    "success": true,
    "message": "PO updated successfully",
    "data": {
        "id": 1,
        "item_id": 123,
        "date": "2024-01-15",
        "po": "PO-2024-001-UPDATED",
        "created_at": "2024-01-15T10:00:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

### 4. Delete PO Record
**DELETE** `/warehouse/po/{id}`

**Response:**
```json
{
    "success": true,
    "message": "PO deleted successfully"
}
```

## Updated Stock Movement Response

The existing `/warehouse/stock-movement/{date}` endpoint now includes PO data:

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "code": "ITEM001",
            "name": "Sample Item",
            "category": "Category A",
            "po": "PO-2024-001",
            "Warehouse A": 100,
            "Warehouse A-requested": 50,
            "Warehouse B": 75,
            "Warehouse B-requested": 25
        }
    ],
    "date": "2024-01-15",
    "warehouses": [
        {
            "id": "WH001",
            "name": "Warehouse A"
        },
        {
            "id": "WH002", 
            "name": "Warehouse B"
        }
    ],
    "total_records": 1
}
```

## Key Features

1. **Separate PO Storage**: PO data is stored independently from stock movement data
2. **Date-based Linking**: PO records are linked to stock movements by item_id and date
3. **Warehouse Aggregation**: PO data is shared across all warehouses for the same item and date
4. **Empty String Default**: If no PO exists for an item/date, an empty string is returned
5. **CRUD Operations**: Full create, read, update, delete functionality for PO records

## Usage Notes

- PO records are unique per item_id and date combination
- PO data is not populated by the sync process - it's user-manual entry only
- When stock movement data is combined across warehouses, the same PO applies to all warehouses for that item/date
- The PO field in stock movement responses will be an empty string if no PO record exists 