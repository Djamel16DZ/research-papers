DROP TABLE IF EXISTS `research_items`;

CREATE TABLE `research_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `classification` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `pdf_file` VARCHAR(255) DEFAULT NULL,
    `meta_data` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_classification` (`classification`),
    FULLTEXT INDEX `ft_paper_search` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertion de données de test avec les métadonnées de référence (Auteurs, Revue, Année, etc.)
INSERT INTO `research_items` (`classification`, `title`, `pdf_file`, `meta_data`) VALUES
(
    'cat_a_plus', 
    'Deep Learning Architectures for Smart Grid Security and Anomaly Detection', 
    NULL, 
    '{"authors": "A. Benali, M. Khelifi, J. Smith", "journal": "IEEE Transactions on Smart Grid", "year": "2025", "volume_issue": "Vol. 16, No. 2", "pages": "112-128", "doi": "10.1109/TSG.2024.3412345"}'
),
(
    'cat_a', 
    'IoT-based Air Quality Monitoring Systems Using Edge Computing in Urban Environments', 
    NULL, 
    '{"authors": "K. Ziane, L. Mecheri", "journal": "Journal of Network and Computer Applications", "year": "2024", "volume_issue": "Vol. 145", "pages": "45-58", "doi": "10.1016/j.jnca.2024.103890"}'
),
(
    'cat_b', 
    'Optimisation des performances des bases de données relationnelles distribuées pour les Systèmes d’Information', 
    NULL, 
    '{"authors": "R. Boudiaf, S. Mansouri", "journal": "Revue des Nouvelles Technologies de l Information", "year": "2023", "volume_issue": "RNTI-E-38", "pages": "112-125", "doi": "10.3166/rnti.38.112-125"}'
);