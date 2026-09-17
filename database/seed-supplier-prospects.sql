INSERT INTO suppliers (name, region, status, notes)
SELECT 'Axion Peptides', 'South Africa', 'Prospect', 'Initial supplier prospect.'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE name = 'Axion Peptides');

INSERT INTO suppliers (name, region, status, notes)
SELECT 'Purist Peptides', 'South Africa', 'Prospect', 'Initial supplier prospect.'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE name = 'Purist Peptides');

INSERT INTO suppliers (name, region, status, notes)
SELECT 'Peptera', 'South Africa', 'Prospect', 'Initial supplier prospect.'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE name = 'Peptera');

INSERT INTO suppliers (name, region, status, notes)
SELECT 'Peak Theory', 'South Africa', 'Prospect', 'Initial supplier prospect.'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE name = 'Peak Theory');

INSERT INTO suppliers (name, region, status, notes)
SELECT 'Reschem', 'South Africa', 'Prospect', 'Initial supplier prospect.'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE name = 'Reschem');

INSERT INTO suppliers (name, region, status, notes)
SELECT 'Peptides Lab', 'South Africa', 'Prospect', 'Initial supplier prospect.'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE name = 'Peptides Lab');

INSERT INTO suppliers (name, region, status, notes)
SELECT 'Pepliful', 'USA', 'Prospect', 'Initial white-label / fulfilment prospect.'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE name = 'Pepliful');

INSERT INTO suppliers (name, region, status, notes)
SELECT 'PeptideDropship', 'USA', 'Prospect', 'Initial white-label / fulfilment prospect.'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE name = 'PeptideDropship');

INSERT INTO suppliers (name, region, status, notes)
SELECT 'Your Peptide Brand (YPB)', 'USA', 'Prospect', 'Initial white-label / fulfilment prospect.'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE name = 'Your Peptide Brand (YPB)');

INSERT INTO suppliers (name, region, status, notes)
SELECT 'Biotech Compounds', 'USA', 'Prospect', 'Additional supplier prospect.'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE name = 'Biotech Compounds');

INSERT INTO suppliers (name, region, status, notes)
SELECT 'PepBoss', 'USA', 'Prospect', 'Additional supplier prospect.'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE name = 'PepBoss');

INSERT INTO suppliers (name, region, status, notes)
SELECT 'PepFulfill', 'USA', 'Prospect', 'Additional supplier prospect.'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE name = 'PepFulfill');
