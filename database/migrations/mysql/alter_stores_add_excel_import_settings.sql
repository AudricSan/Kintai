-- Migration : ajouter excel_import_settings à stores
ALTER TABLE `stores`
    ADD COLUMN `excel_import_settings` JSON NOT NULL DEFAULT '{"col_start":4,"col_end":52,"base_hour":6,"minutes_per_col":30,"block_size":9,"shift_rows":7,"date_row_offsets":[2,0,1,3],"sheet_filter_pattern":"^\\\\d{4}$"}';
