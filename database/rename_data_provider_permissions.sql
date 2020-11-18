UPDATE "public"."permissions" SET "name" = 'admin.operators.create' WHERE "name" = 'admin.providers.create';
UPDATE "public"."permissions" SET "name" = 'admin.operators.read' WHERE "name" = 'admin.providers.read';
UPDATE "public"."permissions" SET "name" = 'admin.operators.update' WHERE "name" = 'admin.providers.update';
UPDATE "public"."permissions" SET "name" = 'admin.operators.delete' WHERE "name" = 'admin.providers.delete';