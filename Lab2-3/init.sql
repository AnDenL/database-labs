DROP DATABASE IF EXISTS farm_db;
CREATE DATABASE farm_db;
USE farm_db;

CREATE TABLE fields (
    field_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    area DECIMAL(8, 2) NOT NULL
);

CREATE TABLE crops (
    crop_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    variety VARCHAR(100) NOT NULL
);

CREATE TABLE workers (
    tab_number INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    position VARCHAR(100) NOT NULL
);

CREATE TABLE warehouses (
    warehouse_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    capacity DECIMAL(10, 2) NOT NULL
);

CREATE TABLE buyers (
    buyer_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL
);

CREATE TABLE operations (
    op_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    op_type VARCHAR(100) NOT NULL,
    op_date DATE NOT NULL,
    planned_hours DECIMAL(6, 2) NOT NULL,
    status ENUM('PLANNED', 'IN_PROGRESS', 'COMPLETED', 'POSTPONED', 'CANCELLED') NOT NULL DEFAULT 'COMPLETED'
);

CREATE TABLE operation_workers (
    op_id INT UNSIGNED NOT NULL,
    tab_number INT UNSIGNED NOT NULL,
    PRIMARY KEY (op_id, tab_number),
    CONSTRAINT fk_op_workers_op FOREIGN KEY (op_id) REFERENCES operations(op_id) ON DELETE CASCADE,
    CONSTRAINT fk_op_workers_worker FOREIGN KEY (tab_number) REFERENCES workers(tab_number) ON DELETE CASCADE
);

CREATE TABLE plowing_ops (
    op_id INT UNSIGNED PRIMARY KEY,
    field_id INT UNSIGNED NOT NULL,
    CONSTRAINT fk_plowing_op FOREIGN KEY (op_id) REFERENCES operations(op_id) ON DELETE CASCADE,
    CONSTRAINT fk_plowing_field FOREIGN KEY (field_id) REFERENCES fields(field_id) ON DELETE CASCADE
);

CREATE TABLE sowing_ops (
    op_id INT UNSIGNED PRIMARY KEY,
    field_id INT UNSIGNED NOT NULL,
    crop_id INT UNSIGNED NOT NULL,
    CONSTRAINT fk_sowing_op FOREIGN KEY (op_id) REFERENCES operations(op_id) ON DELETE CASCADE,
    CONSTRAINT fk_sowing_field FOREIGN KEY (field_id) REFERENCES fields(field_id) ON DELETE CASCADE,
    CONSTRAINT fk_sowing_crop FOREIGN KEY (crop_id) REFERENCES crops(crop_id) ON DELETE CASCADE
);

CREATE TABLE harvesting_ops (
    op_id INT UNSIGNED PRIMARY KEY,
    field_id INT UNSIGNED NOT NULL,
    harvested_tons DECIMAL(10, 2) NOT NULL,
    CONSTRAINT fk_harvesting_op FOREIGN KEY (op_id) REFERENCES operations(op_id) ON DELETE CASCADE,
    CONSTRAINT fk_harvesting_field FOREIGN KEY (field_id) REFERENCES fields(field_id) ON DELETE CASCADE
);

CREATE TABLE transfer_ops (
    op_id INT UNSIGNED PRIMARY KEY,
    crop_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    transferred_tons DECIMAL(10, 2) NOT NULL,
    CONSTRAINT fk_transfer_op FOREIGN KEY (op_id) REFERENCES operations(op_id) ON DELETE CASCADE,
    CONSTRAINT fk_transfer_crop FOREIGN KEY (crop_id) REFERENCES crops(crop_id) ON DELETE CASCADE,
    CONSTRAINT fk_transfer_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id) ON DELETE CASCADE
);

CREATE TABLE sales_ops (
    op_id INT UNSIGNED PRIMARY KEY,
    crop_id INT UNSIGNED NOT NULL,
    buyer_id INT UNSIGNED NOT NULL,
    sold_tons DECIMAL(10, 2) NOT NULL,
    price_per_ton DECIMAL(10, 2) NOT NULL,
    CONSTRAINT fk_sales_op FOREIGN KEY (op_id) REFERENCES operations(op_id) ON DELETE CASCADE,
    CONSTRAINT fk_sales_crop FOREIGN KEY (crop_id) REFERENCES crops(crop_id) ON DELETE CASCADE,
    CONSTRAINT fk_sales_buyer FOREIGN KEY (buyer_id) REFERENCES buyers(buyer_id) ON DELETE CASCADE
);
