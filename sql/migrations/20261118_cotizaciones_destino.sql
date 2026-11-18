-- Agrega el puesto o departamento al que se dirige cada cotización.
ALTER TABLE quotations
    ADD COLUMN client_destination VARCHAR(150) NOT NULL DEFAULT '' AFTER client_name;
