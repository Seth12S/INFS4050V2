-- Drop tables if they exist
DROP TABLE IF EXISTS FedEx_Bonuses;
DROP TABLE IF EXISTS fedex_bonus_data;

-- Create the fedex_bonus_data table
CREATE TABLE fedex_bonus_data (
    id INT PRIMARY KEY,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    fy25_performance_rating INT,
    weighted_performance FLOAT,
    bonus_amount FLOAT
);

-- Create the FedEx_Bonuses table
CREATE TABLE FedEx_Bonuses (
    bonus_id INT AUTO_INCREMENT PRIMARY KEY,
    e_id VARCHAR(50) NOT NULL,
    f_name VARCHAR(50) NOT NULL,
    l_name VARCHAR(50) NOT NULL,
    fy25_performance_rating INT NOT NULL,
    weighted_performance FLOAT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    bonus_date DATE DEFAULT CURRENT_DATE,
    approved_by VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (e_id) REFERENCES FedEx_Employees(e_id),
    FOREIGN KEY (approved_by) REFERENCES FedEx_Employees(e_id)
);

-- Insert sample data into fedex_bonus_data
INSERT INTO fedex_bonus_data (id, first_name, last_name, fy25_performance_rating, weighted_performance, bonus_amount) 
VALUES 
(266495, 'Madeleine', 'Ballard', 2, 0.0015873015873015873, 3968.253968253968),
(18729, 'Gabriella', 'Torres', 10, 0.007936507936507936, 19841.26984126984),
(3065634, 'Zeenat', 'Chamberlain', 7, 0.005555555555555556, 13888.888888888889);

-- Insert data from fedex_bonus_data into FedEx_Bonuses
INSERT INTO FedEx_Bonuses (e_id, f_name, l_name, fy25_performance_rating, weighted_performance, amount, approved_by)
SELECT 
    id as e_id,
    first_name as f_name,
    last_name as l_name,
    fy25_performance_rating,
    weighted_performance,
    bonus_amount as amount,
    'SYSTEM' as approved_by
FROM fedex_bonus_data; 