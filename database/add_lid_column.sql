/* Tambah kolom lid ke tabel members untuk support WAHA GOWS LID format */
ALTER TABLE members ADD COLUMN lid VARCHAR(50) NULL AFTER phone_number;
ALTER TABLE members ADD INDEX idx_lid (lid);
