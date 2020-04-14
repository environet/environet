alter table user_permissions
	add subjectid int;

alter table group_permissions
	add subjectid int;

INSERT INTO "public"."permissions"("name") VALUES

('admin.hydro.monitoringpoint.read'),
('admin.hydro.monitoringpoint.create'),
('admin.hydro.monitoringpoint.update'),
('admin.hydro.monitoringpoint.delete')