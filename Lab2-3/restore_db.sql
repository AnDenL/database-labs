-- 1. Схема баз даних (ЛР1 / ЛР2)
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

-- 2. Наповнення даними
INSERT INTO fields VALUES 
(1, 'Урочище Діброва', 120.50), (2, 'Східний Клин', 85.00), 
(3, 'Поле біля річки', 210.75), (4, 'Південний Масив', 150.00), (5, 'Західна Нива', 95.30);

INSERT INTO crops VALUES 
(1, 'Пшениця озима', 'Подолянка'), (2, 'Кукурудза', 'ДН Пивиха'), 
(3, 'Соняшник', 'НК БDirect'), (4, 'Соя', 'Анушка'), (5, 'Ріпак озимий', 'Черкаський');

INSERT INTO workers VALUES 
(101, 'Іваненко Іван Петрович', 'Тракторист'), (102, 'Петренко Петро Васильович', 'Комбайнер'), 
(103, 'Сидоренко Олексій Ігорович', 'Агроном'), (104, 'Ковальчук Михайло Семенович', 'Водій'), 
(105, 'Мельник Василь Дмитрович', 'Завідувач складу');

INSERT INTO warehouses VALUES 
(1, 'Центральний зернотік', 5000.00), (2, 'Ангар №2 (Східний)', 2000.00), 
(3, 'Склад №3 (Західний)', 1500.00), (4, 'Зерносховище №4', 3000.00), (5, 'Склад №5', 4500.00);

INSERT INTO buyers VALUES 
(1, 'ТОВ «Нібулон»', '+380501112233'), (2, 'ПрАТ «Зернотрейд»', '+380672223344'), 
(3, 'СФГ «Агро-Експрес»', '+380633334455'), (4, 'ТОВ «Поділля-Корм»', '+380994445566'), 
(5, 'ПП «Агрохолдинг Захід»', '+380975556677');

INSERT INTO operations (op_id, op_type, op_date, planned_hours, status) VALUES 
(1, 'Оранка', '2026-03-10', 8.00, 'COMPLETED'), (2, 'Оранка', '2026-03-11', 7.50, 'COMPLETED'), 
(3, 'Оранка', '2026-03-12', 12.00, 'COMPLETED'), (4, 'Оранка', '2026-03-13', 9.00, 'COMPLETED'), (5, 'Оранка', '2026-03-14', 6.50, 'POSTPONED'),
(6, 'Посів', '2026-04-01', 10.00, 'COMPLETED'), (7, 'Посів', '2026-04-02', 8.50, 'COMPLETED'), 
(8, 'Посів', '2026-04-03', 14.00, 'COMPLETED'), (9, 'Посів', '2026-04-04', 11.00, 'COMPLETED'), (10, 'Посів', '2026-04-05', 7.00, 'CANCELLED'),
(11, 'Збирання врожаю', '2026-07-20', 16.00, 'COMPLETED'), (12, 'Збирання врожаю', '2026-07-21', 12.50, 'COMPLETED'), 
(13, 'Збирання врожаю', '2026-07-22', 18.00, 'COMPLETED'), (14, 'Збирання врожаю', '2026-07-23', 15.00, 'COMPLETED'), (15, 'Збирання врожаю', '2026-07-24', 10.00, 'COMPLETED'),
(16, 'Переміщення на склад', '2026-07-25', 6.00, 'COMPLETED'), (17, 'Переміщення на склад', '2026-07-26', 5.50, 'COMPLETED'), 
(18, 'Переміщення на склад', '2026-07-27', 8.00, 'COMPLETED'), (19, 'Переміщення на склад', '2026-07-28', 7.00, 'COMPLETED'), (20, 'Переміщення на склад', '2026-07-29', 4.50, 'COMPLETED'),
(21, 'Продаж партії', '2026-08-05', 3.00, 'IN_PROGRESS'), (22, 'Продаж партії', '2026-08-06', 4.00, 'IN_PROGRESS'), 
(23, 'Продаж партії', '2026-08-07', 3.50, 'IN_PROGRESS'), (24, 'Продаж партії', '2026-08-08', 2.50, 'IN_PROGRESS'), (25, 'Продаж партії', '2026-08-09', 5.00, 'IN_PROGRESS'),
(26, 'Оранка', '2026-03-15', 8.00, 'PLANNED'),
(27, 'Посів', '2026-04-06', 10.00, 'PLANNED');

INSERT INTO operation_workers VALUES (1, 101), (6, 103), (11, 102), (16, 104), (21, 105);
INSERT INTO plowing_ops VALUES (1, 1), (2, 2), (3, 3), (4, 4), (5, 5);
INSERT INTO sowing_ops VALUES (6, 1, 1), (7, 2, 2), (8, 3, 3), (9, 4, 4), (10, 5, 5);
INSERT INTO harvesting_ops VALUES (11, 1, 450.00), (12, 2, 320.50), (13, 3, 780.00), (14, 4, 510.20), (15, 5, 290.00);
INSERT INTO transfer_ops VALUES (16, 1, 1, 400.00), (17, 2, 2, 300.00), (18, 3, 3, 750.00), (19, 4, 4, 480.00), (20, 5, 5, 270.00);
INSERT INTO sales_ops VALUES (21, 1, 1, 350.00, 7200.00), (22, 2, 2, 280.00, 6100.50), (23, 3, 3, 700.00, 14500.00), (24, 4, 4, 450.00, 13200.00), (25, 5, 5, 250.00, 16800.00);

-- 3. Додаткові об'єкти ЛР3 (ALTER, VIEWs, TRIGGER)
ALTER TABLE fields ADD COLUMN actual INT NOT NULL DEFAULT 1;

UPDATE fields SET actual = 0 WHERE field_id IN (2, 4);

CREATE VIEW v_active_fields AS 
SELECT field_id, name, area, actual 
FROM fields WHERE actual = 1;

CREATE VIEW v_sales_report AS 
SELECT s.op_id, o.op_date, c.name AS crop_name, b.name AS buyer_name, 
       s.sold_tons, s.price_per_ton, (s.sold_tons * s.price_per_ton) AS total_sum 
FROM sales_ops s 
JOIN operations o ON s.op_id = o.op_id 
JOIN crops c ON s.crop_id = c.crop_id 
JOIN buyers b ON s.buyer_id = b.buyer_id;

DELIMITER $$
CREATE TRIGGER prevent_active_field_delete 
BEFORE DELETE ON fields 
FOR EACH ROW 
BEGIN 
    IF OLD.actual = 1 THEN 
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Error: You are not allowed to delete the current database record!'; 
    END IF; 
END $$
DELIMITER ;