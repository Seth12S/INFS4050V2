-- Drop the table if it exists to avoid conflicts
DROP TABLE IF EXISTS FedEx_Bonuses;

-- Create the bonus table with the correct structure
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

-- Insert the data from fedex_bonus_data
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