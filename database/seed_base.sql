-- Static reference data for Tunisia PC Advisor.
-- This file is included automatically by tools/build_seed.php, which writes
-- database/seed.sql with the verified catalogue.

USE tunisia_pc_advisor;
SET NAMES utf8mb4;

INSERT INTO brands (id, name) VALUES
    (1, 'ASUS'), (2, 'HP'), (3, 'Lenovo'), (4, 'Dell'), (5, 'Acer'),
    (6, 'MSI'), (7, 'Apple'), (8, 'Gigabyte'), (9, 'Samsung'), (10, 'Other');

INSERT INTO retailers (id, name, slug, website_url, is_active) VALUES
    (1, 'Tunisia Net', 'tunisia-net', 'https://www.tunisianet.com.tn/', 1),
    (2, 'Mytek', 'mytek', 'https://www.mytek.tn/', 1),
    (3, 'SpaceNet', 'spacenet', 'https://spacenet.tn/', 1),
    (4, 'SBS Informatique', 'sbs-informatique', 'https://www.sbsinformatique.com/', 1),
    (5, 'Wiki', 'wiki', 'https://wiki.tn/', 1);

INSERT INTO activities (id, slug, name, description, icon) VALUES
    (1, 'student', 'Student', 'Coursework, research, writing and everyday academic work.', 'graduation-cap'),
    (2, 'software-developer', 'Software Developer', 'Building, testing and running software projects locally.', 'code-2'),
    (3, 'web-developer', 'Web Developer', 'Front-end and back-end web development, browsers and local servers.', 'globe-2'),
    (4, 'mobile-developer', 'Mobile Application Developer', 'Mobile SDKs, emulators, simulators and build tooling.', 'smartphone'),
    (5, 'graphic-designer', 'Graphic Designer', 'Visual design, image editing and colour-sensitive creative work.', 'palette'),
    (6, 'ui-ux-designer', 'UI/UX Designer', 'Interface design, prototyping, research and design systems.', 'pen-tool'),
    (7, 'video-editor', 'Video Editor', 'Editing, colour work, encoding and media-heavy timelines.', 'film'),
    (8, '3d-designer', '3D Designer / 3D Artist', 'Modelling, texturing, rendering and real-time 3D work.', 'box'),
    (9, 'architect', 'Architect', 'Architectural modelling, drawings and visualisation.', 'drafting-compass'),
    (10, 'engineer', 'Engineer', 'Technical design, simulation and engineering documentation.', 'cog'),
    (11, 'data-scientist', 'Data Scientist', 'Data preparation, analysis, notebooks and model experiments.', 'bar-chart-3'),
    (12, 'ai-ml', 'Artificial Intelligence / Machine Learning', 'Local model training, experiments and GPU-accelerated workloads.', 'brain-circuit'),
    (13, 'cybersecurity', 'Cybersecurity Specialist', 'Security tooling, virtual labs, analysis and administration.', 'shield-check'),
    (14, 'office-worker', 'Office Worker', 'Documents, spreadsheets, email, presentations and web applications.', 'briefcase'),
    (15, 'business-manager', 'Business Manager', 'Management tasks, reporting, communication and travel.', 'trending-up'),
    (16, 'gamer', 'Gamer', 'Gaming, game libraries and graphics-intensive titles.', 'gamepad-2'),
    (17, 'content-creator', 'Content Creator', 'Recording, streaming, editing and publishing digital content.', 'camera'),
    (18, 'general-use', 'General Personal Use', 'Everyday browsing, media, communication and light productivity.', 'monitor'),
    (19, 'other', 'Other', 'A different workflow that does not fit the categories above.', 'help-circle');

INSERT INTO activity_requirements
    (activity_id, minimum_cpu_level, preferred_cpu_level, minimum_ram_gb, preferred_ram_gb,
     minimum_storage_gb, preferred_storage_gb, gpu_requirement, minimum_gpu_vram_gb,
     portability_importance, cpu_weight, ram_weight, gpu_weight, storage_weight,
     budget_weight, activity_weight, preferences_weight, notes)
VALUES
    (1, 3.5, 5.5, 8, 16, 256, 512, 'integrated_ok', 0, 'medium', 20, 17, 8, 12, 25, 11, 7, 'Prioritise a responsive SSD and enough memory for browser-heavy coursework.'),
    (2, 5.5, 7.5, 16, 32, 512, 1024, 'integrated_ok', 0, 'medium', 24, 22, 8, 12, 18, 11, 5, 'CPU and memory matter more than a discrete GPU for ordinary development.'),
    (3, 5.0, 7.0, 16, 32, 512, 1024, 'integrated_ok', 0, 'medium', 22, 20, 8, 12, 22, 11, 5, 'Comfortable multitasking with browsers, editors and local servers.'),
    (4, 5.5, 7.5, 16, 32, 512, 1024, 'integrated_ok', 0, 'medium', 22, 22, 8, 12, 20, 11, 5, 'Emulators and build tools benefit from CPU headroom and 16 GB or more RAM.'),
    (5, 5.5, 7.5, 16, 32, 512, 1024, 'dedicated_preferred', 4, 'medium', 18, 18, 20, 12, 18, 8, 6, 'Panel quality and a dedicated GPU help; verify colour claims before purchase.'),
    (6, 5.0, 7.0, 16, 32, 512, 1024, 'integrated_ok', 0, 'medium', 18, 18, 16, 10, 22, 10, 6, 'Fast multitasking and a good display are more important than raw GPU power.'),
    (7, 6.5, 8.5, 16, 32, 1024, 2048, 'dedicated_preferred', 4, 'medium', 20, 20, 22, 15, 10, 8, 5, 'Media caches are large; a fast 1 TB SSD and a capable GPU reduce bottlenecks.'),
    (8, 7.0, 8.5, 16, 32, 1024, 2048, 'dedicated_preferred', 4, 'low', 18, 20, 25, 12, 10, 9, 6, 'GPU acceleration, memory and cooling headroom matter for 3D pipelines.'),
    (9, 6.5, 8.0, 16, 32, 1024, 2048, 'dedicated_preferred', 4, 'medium', 18, 20, 24, 12, 12, 8, 6, 'CAD and visualisation benefit from CPU, memory and a discrete GPU.'),
    (10, 5.5, 7.5, 16, 32, 512, 1024, 'dedicated_preferred', 4, 'medium', 20, 18, 18, 12, 18, 8, 6, 'Mixed engineering workloads need balanced CPU, memory and graphics.'),
    (11, 6.5, 8.0, 16, 32, 512, 1024, 'dedicated_preferred', 4, 'medium', 20, 22, 18, 12, 14, 9, 5, 'Memory capacity is valuable for data preparation and local experiments.'),
    (12, 7.0, 8.5, 16, 32, 1024, 2048, 'dedicated_preferred', 6, 'low', 18, 22, 24, 12, 12, 7, 5, 'A discrete GPU with more VRAM helps local accelerated workloads; it is not mandatory for every task.'),
    (13, 5.5, 7.5, 16, 32, 512, 1024, 'integrated_ok', 0, 'medium', 24, 20, 6, 12, 20, 12, 6, 'Virtual labs and analysis tools favour CPU and RAM over gaming graphics.'),
    (14, 2.5, 4.5, 8, 16, 256, 512, 'none', 0, 'low', 22, 18, 5, 12, 25, 11, 7, 'A reliable SSD, adequate memory and a comfortable display are the priorities.'),
    (15, 2.5, 4.5, 8, 16, 256, 512, 'none', 0, 'high', 20, 18, 5, 12, 27, 11, 7, 'Portability, battery information and a good display support frequent travel.'),
    (16, 6.0, 7.5, 16, 32, 512, 1024, 'dedicated_required', 4, 'low', 16, 14, 32, 10, 14, 9, 5, 'A discrete GPU is a mandatory preference; the engine prioritises graphics headroom.'),
    (17, 6.0, 7.5, 16, 32, 1024, 2048, 'dedicated_preferred', 4, 'medium', 18, 18, 20, 14, 16, 9, 5, 'Recording and editing need storage speed, memory and graphics acceleration.'),
    (18, 2.5, 4.5, 8, 16, 256, 512, 'none', 0, 'medium', 18, 16, 10, 12, 26, 11, 7, 'Balanced everyday performance with sensible value.'),
    (19, 3.5, 5.5, 8, 16, 256, 512, 'integrated_ok', 0, 'medium', 18, 18, 14, 12, 22, 10, 6, 'Flexible profile for mixed or unspecified work.');

INSERT INTO workloads
    (id, slug, name, description, minimum_cpu_level, minimum_ram_gb, gpu_requirement, minimum_gpu_vram_gb, minimum_storage_gb)
VALUES
    (1, 'programming', 'Programming', 'Code editors, compilers, debugging and local tooling.', 5.5, 16, 'integrated_ok', 0, 512),
    (2, 'web-development', 'Web development', 'Browsers, dev servers, build tools and design hand-off.', 5.0, 16, 'integrated_ok', 0, 512),
    (3, 'android-development', 'Android development', 'Android Studio, emulators and Gradle builds.', 5.5, 16, 'integrated_ok', 0, 512),
    (4, 'ios-development', 'iOS development', 'Apple development tooling and simulators where supported.', 6.5, 16, 'integrated_ok', 0, 512),
    (5, 'virtual-machines', 'Virtual machines', 'Multiple guest operating systems and isolated labs.', 6.0, 16, 'integrated_ok', 0, 512),
    (6, 'docker-containers', 'Docker and containers', 'Container images, local services and orchestration.', 5.5, 16, 'integrated_ok', 0, 512),
    (7, 'office-applications', 'Office applications', 'Documents, spreadsheets, presentations and email.', 2.0, 8, 'none', 0, 256),
    (8, 'internet-browsing', 'Internet browsing', 'Everyday web, media and communication.', 2.0, 8, 'none', 0, 256),
    (9, 'graphic-design', 'Graphic design', 'Layout, image editing and visual asset production.', 5.5, 16, 'dedicated_preferred', 4, 512),
    (10, 'photoshop-illustrator', 'Photoshop and Illustrator', 'Layered image editing and vector artwork.', 5.0, 16, 'dedicated_preferred', 4, 512),
    (11, 'video-editing', 'Video editing', 'Timeline editing, effects, encoding and media caches.', 6.0, 16, 'dedicated_preferred', 4, 1024),
    (12, '3d-modeling', '3D modeling', 'Modelling, texturing, preview rendering and real-time 3D.', 7.0, 32, 'dedicated_preferred', 6, 1024),
    (13, 'cad-engineering', 'CAD and engineering software', 'Technical drawings, CAD models and engineering documents.', 6.5, 16, 'dedicated_preferred', 4, 512),
    (14, 'gaming', 'Gaming', 'Game titles at the display resolution and settings you choose.', 6.0, 16, 'dedicated_required', 4, 512),
    (15, 'streaming', 'Streaming', 'Live capture, encoding and interactive broadcasting.', 6.0, 16, 'dedicated_preferred', 4, 512),
    (16, 'data-analysis', 'Data analysis', 'Spreadsheets, notebooks, queries and visualisation.', 6.0, 16, 'integrated_ok', 0, 512),
    (17, 'ai-development', 'AI development', 'Local model experiments, GPU workloads and large datasets.', 7.0, 32, 'dedicated_preferred', 8, 1024),
    (18, 'general-multitasking', 'General multitasking', 'Many browser tabs and everyday applications at once.', 4.0, 8, 'none', 0, 512);

INSERT INTO activity_workloads (activity_id, workload_id, relevance) VALUES
    (1, 7, 3), (1, 8, 3), (1, 18, 2), (1, 1, 1), (1, 2, 1),
    (2, 1, 3), (2, 2, 2), (2, 6, 2), (2, 5, 2), (2, 7, 1), (2, 18, 1),
    (3, 2, 3), (3, 1, 3), (3, 6, 2), (3, 10, 1), (3, 18, 1),
    (4, 3, 3), (4, 4, 3), (4, 1, 2), (4, 6, 2), (4, 5, 2), (4, 18, 1),
    (5, 9, 3), (5, 10, 3), (5, 11, 1), (5, 12, 1), (5, 18, 1),
    (6, 9, 3), (6, 10, 3), (6, 7, 2), (6, 18, 2),
    (7, 11, 3), (7, 15, 2), (7, 9, 2), (7, 10, 1), (7, 16, 1),
    (8, 12, 3), (8, 13, 2), (8, 11, 2), (8, 9, 1), (8, 15, 1),
    (9, 13, 3), (9, 12, 3), (9, 9, 2), (9, 11, 1), (9, 7, 1),
    (10, 13, 3), (10, 16, 2), (10, 12, 2), (10, 1, 1), (10, 7, 1), (10, 18, 1),
    (11, 16, 3), (11, 1, 2), (11, 5, 2), (11, 17, 2), (11, 18, 1),
    (12, 17, 3), (12, 16, 3), (12, 5, 2), (12, 1, 2), (12, 6, 2),
    (13, 1, 3), (13, 6, 2), (13, 5, 2), (13, 7, 2), (13, 18, 1),
    (14, 7, 3), (14, 8, 3), (14, 18, 2),
    (15, 7, 3), (15, 8, 3), (15, 18, 2),
    (16, 14, 3), (16, 15, 2), (16, 8, 1), (16, 18, 1),
    (17, 11, 3), (17, 15, 3), (17, 9, 2), (17, 10, 2), (17, 7, 1),
    (18, 8, 3), (18, 7, 2), (18, 18, 2), (18, 1, 1),
    (19, 18, 3), (19, 8, 2), (19, 7, 2), (19, 1, 1);

-- No administrator is seeded: run `php tools/create_admin.php` after import.
