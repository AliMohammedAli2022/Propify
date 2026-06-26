-- Propify initial MySQL schema draft.
-- This file is a backend contract until Laravel migrations are generated.

CREATE TABLE roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  display_name VARCHAR(150) NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);

CREATE TABLE permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  display_name VARCHAR(180) NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);

CREATE TABLE role_permission (
  role_id BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(id),
  FOREIGN KEY (permission_id) REFERENCES permissions(id)
);

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id BIGINT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(20) NULL,
  password VARCHAR(255) NOT NULL,
  avatar_path VARCHAR(255) NULL,
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (role_id) REFERENCES roles(id)
);

CREATE TABLE clients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  role ENUM('seller', 'buyer', 'lessor', 'tenant', 'witness', 'broker', 'owner') NOT NULL,
  phone VARCHAR(11) NOT NULL,
  address VARCHAR(255) NULL,
  national_id VARCHAR(80) NULL,
  national_id_image_path VARCHAR(255) NULL,
  source VARCHAR(120) NULL,
  stage VARCHAR(120) NOT NULL DEFAULT 'lead',
  notes TEXT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  INDEX clients_phone_index (phone)
);

CREATE TABLE properties (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  owner_client_id BIGINT UNSIGNED NULL,
  type VARCHAR(100) NOT NULL,
  purpose ENUM('sale', 'rent', 'installment_sale') NOT NULL,
  status ENUM('available', 'reserved', 'sold', 'rented', 'pending_review', 'rejected', 'archived') NOT NULL DEFAULT 'pending_review',
  title VARCHAR(190) NULL,
  province VARCHAR(120) NOT NULL,
  area VARCHAR(160) NOT NULL,
  address VARCHAR(255) NULL,
  space DECIMAL(12,2) NOT NULL,
  floors INT UNSIGNED NULL,
  rooms INT UNSIGNED NULL,
  bathrooms INT UNSIGNED NULL,
  facade VARCHAR(120) NULL,
  building_age INT UNSIGNED NULL,
  price DECIMAL(15,2) NOT NULL,
  negotiable BOOLEAN NOT NULL DEFAULT TRUE,
  video_url VARCHAR(255) NULL,
  description TEXT NULL,
  internal_notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (owner_client_id) REFERENCES clients(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (approved_by) REFERENCES users(id),
  INDEX properties_status_index (status),
  INDEX properties_area_index (area)
);

CREATE TABLE property_media (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  property_id BIGINT UNSIGNED NOT NULL,
  kind ENUM('image', 'video', 'document') NOT NULL,
  path VARCHAR(255) NOT NULL,
  caption VARCHAR(190) NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (property_id) REFERENCES properties(id)
);

CREATE TABLE contracts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  property_id BIGINT UNSIGNED NOT NULL,
  seller_client_id BIGINT UNSIGNED NULL,
  buyer_client_id BIGINT UNSIGNED NULL,
  lessor_client_id BIGINT UNSIGNED NULL,
  tenant_client_id BIGINT UNSIGNED NULL,
  type ENUM('cash_sale', 'installment_sale', 'rent') NOT NULL,
  status ENUM('draft', 'active', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
  total_amount DECIMAL(15,2) NOT NULL,
  down_payment DECIMAL(15,2) NOT NULL DEFAULT 0,
  remaining_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  office_commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  office_commission_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  starts_at DATE NULL,
  ends_at DATE NULL,
  terms TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (property_id) REFERENCES properties(id),
  FOREIGN KEY (seller_client_id) REFERENCES clients(id),
  FOREIGN KEY (buyer_client_id) REFERENCES clients(id),
  FOREIGN KEY (lessor_client_id) REFERENCES clients(id),
  FOREIGN KEY (tenant_client_id) REFERENCES clients(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE installments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id BIGINT UNSIGNED NOT NULL,
  number INT UNSIGNED NOT NULL,
  due_date DATE NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  status ENUM('pending', 'paid', 'late', 'cancelled') NOT NULL DEFAULT 'pending',
  paid_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (contract_id) REFERENCES contracts(id),
  INDEX installments_due_date_index (due_date),
  INDEX installments_status_index (status)
);

CREATE TABLE vouchers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  type ENUM('receipt', 'payment') NOT NULL,
  client_id BIGINT UNSIGNED NULL,
  property_id BIGINT UNSIGNED NULL,
  contract_id BIGINT UNSIGNED NULL,
  amount DECIMAL(15,2) NOT NULL,
  reason VARCHAR(255) NOT NULL,
  issued_at DATE NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (client_id) REFERENCES clients(id),
  FOREIGN KEY (property_id) REFERENCES properties(id),
  FOREIGN KEY (contract_id) REFERENCES contracts(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE ledger_entries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voucher_id BIGINT UNSIGNED NULL,
  contract_id BIGINT UNSIGNED NULL,
  direction ENUM('debit', 'credit') NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  description VARCHAR(255) NOT NULL,
  entry_date DATE NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (voucher_id) REFERENCES vouchers(id),
  FOREIGN KEY (contract_id) REFERENCES contracts(id),
  INDEX ledger_entry_date_index (entry_date)
);

CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  type VARCHAR(120) NOT NULL,
  title VARCHAR(190) NOT NULL,
  body TEXT NULL,
  read_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
