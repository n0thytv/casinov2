-- =============================================
-- N0THY CASINO - BLACKJACK DATABASE SCHEMA
-- =============================================

-- Compte Banque du Casino
CREATE TABLE IF NOT EXISTS casino_bank (
    id INT PRIMARY KEY AUTO_INCREMENT,
    balance BIGINT DEFAULT 1000000,          -- Solde initial: 1 Million N0thyCoins
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Initialiser le compte casino s'il n'existe pas
INSERT INTO casino_bank (balance) 
SELECT 1000000 
WHERE NOT EXISTS (SELECT 1 FROM casino_bank);

-- Tables de Blackjack
CREATE TABLE IF NOT EXISTS blackjack_tables (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    status ENUM('waiting', 'betting', 'playing', 'finished') DEFAULT 'waiting',
    min_bet INT DEFAULT 100,
    max_bet INT DEFAULT 10000,
    dealer_cards JSON DEFAULT NULL,          -- Cartes du croupier [{"suit":"hearts","value":"A"}, ...]
    streamer_mode BOOLEAN DEFAULT FALSE,     -- Mode Streameur actif
    current_player_seat INT DEFAULT NULL,    -- Siège du joueur actuel (1-5)
    created_by INT,                          -- Admin qui a créé la table
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Joueurs à une table
CREATE TABLE IF NOT EXISTS blackjack_players (
    id INT PRIMARY KEY AUTO_INCREMENT,
    table_id INT NOT NULL,
    user_id INT NOT NULL,
    seat_number INT NOT NULL,                -- Position 1-5 sur la table
    bet_amount INT DEFAULT 0,                -- Mise du joueur (main principale)
    cards JSON DEFAULT NULL,                 -- Cartes du joueur (main principale)
    status ENUM('betting', 'waiting', 'playing', 'stand', 'bust', 'blackjack', 'win', 'lose', 'push') DEFAULT 'betting',
    doubled BOOLEAN DEFAULT FALSE,           -- A doublé sa mise
    -- SPLIT SUPPORT
    has_split BOOLEAN DEFAULT FALSE,         -- Le joueur a splitté
    split_cards JSON DEFAULT NULL,           -- Cartes de la 2ème main (après split)
    split_status ENUM('playing', 'stand', 'bust', 'win', 'lose', 'push') DEFAULT NULL,
    split_doubled BOOLEAN DEFAULT FALSE,     -- A doublé la main splittée
    current_hand ENUM('main', 'split') DEFAULT 'main', -- Quelle main est en cours
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (table_id) REFERENCES blackjack_tables(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_seat (table_id, seat_number),
    UNIQUE KEY unique_player_table (table_id, user_id)
);

-- Historique des transactions Casino
CREATE TABLE IF NOT EXISTS casino_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    table_id INT NULL,
    user_id INT NULL,
    type ENUM('bet', 'win', 'blackjack_win', 'push', 'loss', 'double', 'deposit', 'withdrawal') NOT NULL,
    amount INT NOT NULL,
    balance_after BIGINT,                    -- Solde casino après transaction
    description VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (table_id) REFERENCES blackjack_tables(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Index pour performances
CREATE INDEX idx_tables_status ON blackjack_tables(status);
CREATE INDEX idx_players_table ON blackjack_players(table_id);
CREATE INDEX idx_transactions_table ON casino_transactions(table_id);
CREATE INDEX idx_transactions_user ON casino_transactions(user_id);
