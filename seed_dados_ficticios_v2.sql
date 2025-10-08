-- =====================================================
-- Script de População de Dados Fictícios - VERSÃO CORRIGIDA
-- Sistema de Controle de Celulares - Beta Preview
-- Version: 1.7.0
-- =====================================================
-- IMPORTANTE: 
-- 1. Substitua 'wp_' pelo prefixo correto do seu WordPress
-- 2. Execute TODO o script de uma vez só no phpMyAdmin
-- 3. Usa variáveis para capturar IDs automáticos
-- =====================================================

-- =====================================================
-- COLABORADOR 1: João Silva
-- =====================================================
INSERT INTO wp_colaboradores (nome, sobrenome, matricula) VALUES ('João', 'Silva', 'MAT001');
SET @colab1 = LAST_INSERT_ID();
INSERT INTO wp_colaboradores_meta (colaborador_id, meta_key, meta_value) VALUES
(@colab1, 'setor', 'Operações'),
(@colab1, 'local', 'São Paulo - SP');

-- =====================================================
-- COLABORADOR 2: Maria Santos
-- =====================================================
INSERT INTO wp_colaboradores (nome, sobrenome, matricula) VALUES ('Maria', 'Santos', 'MAT002');
SET @colab2 = LAST_INSERT_ID();
INSERT INTO wp_colaboradores_meta (colaborador_id, meta_key, meta_value) VALUES
(@colab2, 'setor', 'Vendas'),
(@colab2, 'local', 'Rio de Janeiro - RJ');

-- =====================================================
-- COLABORADOR 3: Pedro Oliveira
-- =====================================================
INSERT INTO wp_colaboradores (nome, sobrenome, matricula) VALUES ('Pedro', 'Oliveira', 'MAT003');
SET @colab3 = LAST_INSERT_ID();
INSERT INTO wp_colaboradores_meta (colaborador_id, meta_key, meta_value) VALUES
(@colab3, 'setor', 'TI'),
(@colab3, 'local', 'São Paulo - SP');

-- =====================================================
-- COLABORADOR 4: Ana Costa
-- =====================================================
INSERT INTO wp_colaboradores (nome, sobrenome, matricula) VALUES ('Ana', 'Costa', 'MAT004');
SET @colab4 = LAST_INSERT_ID();
INSERT INTO wp_colaboradores_meta (colaborador_id, meta_key, meta_value) VALUES
(@colab4, 'setor', 'Financeiro'),
(@colab4, 'local', 'Belo Horizonte - MG');

-- =====================================================
-- COLABORADOR 5: Carlos Ferreira
-- =====================================================
INSERT INTO wp_colaboradores (nome, sobrenome, matricula) VALUES ('Carlos', 'Ferreira', 'MAT005');
SET @colab5 = LAST_INSERT_ID();
INSERT INTO wp_colaboradores_meta (colaborador_id, meta_key, meta_value) VALUES
(@colab5, 'setor', 'Logística'),
(@colab5, 'local', 'Curitiba - PR');

-- =====================================================
-- COLABORADOR 6: Juliana Almeida
-- =====================================================
INSERT INTO wp_colaboradores (nome, sobrenome, matricula) VALUES ('Juliana', 'Almeida', 'MAT006');
SET @colab6 = LAST_INSERT_ID();
INSERT INTO wp_colaboradores_meta (colaborador_id, meta_key, meta_value) VALUES
(@colab6, 'setor', 'RH'),
(@colab6, 'local', 'São Paulo - SP');

-- =====================================================
-- COLABORADOR 7: Roberto Lima
-- =====================================================
INSERT INTO wp_colaboradores (nome, sobrenome, matricula) VALUES ('Roberto', 'Lima', 'MAT007');
SET @colab7 = LAST_INSERT_ID();
INSERT INTO wp_colaboradores_meta (colaborador_id, meta_key, meta_value) VALUES
(@colab7, 'setor', 'Marketing'),
(@colab7, 'local', 'Porto Alegre - RS');

-- =====================================================
-- COLABORADOR 8: Fernanda Rodrigues
-- =====================================================
INSERT INTO wp_colaboradores (nome, sobrenome, matricula) VALUES ('Fernanda', 'Rodrigues', 'MAT008');
SET @colab8 = LAST_INSERT_ID();
INSERT INTO wp_colaboradores_meta (colaborador_id, meta_key, meta_value) VALUES
(@colab8, 'setor', 'Operações'),
(@colab8, 'local', 'Brasília - DF');

-- =====================================================
-- COLABORADOR 9: Lucas Martins
-- =====================================================
INSERT INTO wp_colaboradores (nome, sobrenome, matricula) VALUES ('Lucas', 'Martins', 'MAT009');
SET @colab9 = LAST_INSERT_ID();
INSERT INTO wp_colaboradores_meta (colaborador_id, meta_key, meta_value) VALUES
(@colab9, 'setor', 'TI'),
(@colab9, 'local', 'São Paulo - SP');

-- =====================================================
-- COLABORADOR 10: Patricia Souza
-- =====================================================
INSERT INTO wp_colaboradores (nome, sobrenome, matricula) VALUES ('Patricia', 'Souza', 'MAT010');
SET @colab10 = LAST_INSERT_ID();
INSERT INTO wp_colaboradores_meta (colaborador_id, meta_key, meta_value) VALUES
(@colab10, 'setor', 'Vendas'),
(@colab10, 'local', 'Salvador - BA');

-- =====================================================
-- CELULAR 1: Samsung Galaxy A54 (Emprestado - João)
-- =====================================================
INSERT INTO wp_celulares (marca, modelo, colaborador, status) VALUES ('Samsung', 'Galaxy A54', @colab1, 'emprestado');
SET @cel1 = LAST_INSERT_ID();
INSERT INTO wp_celulares_meta (celular_id, meta_key, meta_value) VALUES
(@cel1, 'imei', '352991234567890'),
(@cel1, 'serial number', 'SN-SAM-A54-001'),
(@cel1, 'data_aquisicao', '2024-01-15'),
(@cel1, 'data_entrega', '2024-01-20'),
(@cel1, 'propriedade', 'Metalife'),
(@cel1, 'selb', 'SELB-2024-001'),
(@cel1, 'observacao', 'Aparelho em perfeito estado, entregue com capa e película.');

-- =====================================================
-- CELULAR 2: iPhone 13 (Emprestado - Maria)
-- =====================================================
INSERT INTO wp_celulares (marca, modelo, colaborador, status) VALUES ('Apple', 'iPhone 13', @colab2, 'emprestado');
SET @cel2 = LAST_INSERT_ID();
INSERT INTO wp_celulares_meta (celular_id, meta_key, meta_value) VALUES
(@cel2, 'imei', '359876543210987'),
(@cel2, 'serial number', 'SN-APL-IP13-002'),
(@cel2, 'data_aquisicao', '2024-02-10'),
(@cel2, 'data_entrega', '2024-02-15'),
(@cel2, 'propriedade', 'Selbetti'),
(@cel2, 'selb', 'SELB-2024-002'),
(@cel2, 'observacao', 'iPhone 13 128GB, cor azul.');

-- =====================================================
-- CELULAR 3: Moto G82 (Emprestado - Pedro)
-- =====================================================
INSERT INTO wp_celulares (marca, modelo, colaborador, status) VALUES ('Motorola', 'Moto G82', @colab3, 'emprestado');
SET @cel3 = LAST_INSERT_ID();
INSERT INTO wp_celulares_meta (celular_id, meta_key, meta_value) VALUES
(@cel3, 'imei', '354123456789012'),
(@cel3, 'serial number', 'SN-MOT-G82-003'),
(@cel3, 'data_aquisicao', '2024-01-25'),
(@cel3, 'data_entrega', '2024-02-01'),
(@cel3, 'propriedade', 'Metalife'),
(@cel3, 'selb', 'SELB-2024-003'),
(@cel3, 'observacao', 'Celular corporativo para equipe de TI.');

-- =====================================================
-- CELULAR 4: Galaxy S21 (Emprestado - Ana)
-- =====================================================
INSERT INTO wp_celulares (marca, modelo, colaborador, status) VALUES ('Samsung', 'Galaxy S21', @colab4, 'emprestado');
SET @cel4 = LAST_INSERT_ID();
INSERT INTO wp_celulares_meta (celular_id, meta_key, meta_value) VALUES
(@cel4, 'imei', '358765432109876'),
(@cel4, 'serial number', 'SN-SAM-S21-004'),
(@cel4, 'data_aquisicao', '2023-12-05'),
(@cel4, 'data_entrega', '2023-12-10'),
(@cel4, 'propriedade', 'Metalife'),
(@cel4, 'selb', 'SELB-2023-045'),
(@cel4, 'observacao', 'Aparelho premium para gerência.');

-- =====================================================
-- CELULAR 5: Redmi Note 12 (Emprestado - Carlos)
-- =====================================================
INSERT INTO wp_celulares (marca, modelo, colaborador, status) VALUES ('Xiaomi', 'Redmi Note 12', @colab5, 'emprestado');
SET @cel5 = LAST_INSERT_ID();
INSERT INTO wp_celulares_meta (celular_id, meta_key, meta_value) VALUES
(@cel5, 'imei', '351234567890123'),
(@cel5, 'serial number', 'SN-XIA-RN12-005'),
(@cel5, 'data_aquisicao', '2024-03-01'),
(@cel5, 'data_entrega', '2024-03-05'),
(@cel5, 'propriedade', 'Selbetti'),
(@cel5, 'selb', 'SELB-2024-005'),
(@cel5, 'observacao', 'Celular para equipe de logística.');

-- =====================================================
-- CELULAR 6: iPhone 14 (Emprestado - Juliana)
-- =====================================================
INSERT INTO wp_celulares (marca, modelo, colaborador, status) VALUES ('Apple', 'iPhone 14', @colab6, 'emprestado');
SET @cel6 = LAST_INSERT_ID();
INSERT INTO wp_celulares_meta (celular_id, meta_key, meta_value) VALUES
(@cel6, 'imei', '357890123456789'),
(@cel6, 'serial number', 'SN-APL-IP14-006'),
(@cel6, 'data_aquisicao', '2024-02-20'),
(@cel6, 'data_entrega', '2024-02-25'),
(@cel6, 'propriedade', 'Metalife'),
(@cel6, 'selb', 'SELB-2024-006'),
(@cel6, 'observacao', 'iPhone 14 Pro 256GB, cor preta.');

-- =====================================================
-- CELULAR 7: Galaxy A34 (Disponível - Sem colaborador)
-- =====================================================
INSERT INTO wp_celulares (marca, modelo, colaborador, status) VALUES ('Samsung', 'Galaxy A34', NULL, 'disponivel');
SET @cel7 = LAST_INSERT_ID();
INSERT INTO wp_celulares_meta (celular_id, meta_key, meta_value) VALUES
(@cel7, 'imei', '353456789012345'),
(@cel7, 'serial number', 'SN-SAM-A34-007'),
(@cel7, 'data_aquisicao', '2024-03-10'),
(@cel7, 'propriedade', 'Metalife'),
(@cel7, 'selb', 'SELB-2024-007'),
(@cel7, 'observacao', 'Aparelho novo, aguardando alocação.');

-- =====================================================
-- CELULAR 8: Motorola Edge 40 (Emprestado - Roberto)
-- =====================================================
INSERT INTO wp_celulares (marca, modelo, colaborador, status) VALUES ('Motorola', 'Edge 40', @colab7, 'emprestado');
SET @cel8 = LAST_INSERT_ID();
INSERT INTO wp_celulares_meta (celular_id, meta_key, meta_value) VALUES
(@cel8, 'imei', '356789012345678'),
(@cel8, 'serial number', 'SN-MOT-E40-008'),
(@cel8, 'data_aquisicao', '2024-01-30'),
(@cel8, 'data_entrega', '2024-02-05'),
(@cel8, 'propriedade', 'Selbetti'),
(@cel8, 'selb', 'SELB-2024-008'),
(@cel8, 'observacao', 'Celular para equipe de marketing.');

-- =====================================================
-- CELULAR 9: Poco X5 (Manutenção - Sem colaborador)
-- =====================================================
INSERT INTO wp_celulares (marca, modelo, colaborador, status) VALUES ('Xiaomi', 'Poco X5', NULL, 'manutencao');
SET @cel9 = LAST_INSERT_ID();
INSERT INTO wp_celulares_meta (celular_id, meta_key, meta_value) VALUES
(@cel9, 'imei', '352345678901234'),
(@cel9, 'serial number', 'SN-XIA-PX5-009'),
(@cel9, 'data_aquisicao', '2024-02-15'),
(@cel9, 'propriedade', 'Metalife'),
(@cel9, 'selb', 'SELB-2024-009'),
(@cel9, 'observacao', 'Aparelho em manutenção - problema na bateria.');

-- =====================================================
-- CELULAR 10: Galaxy M54 (Defeito - Fernanda)
-- =====================================================
INSERT INTO wp_celulares (marca, modelo, colaborador, status) VALUES ('Samsung', 'Galaxy M54', @colab8, 'defeito');
SET @cel10 = LAST_INSERT_ID();
INSERT INTO wp_celulares_meta (celular_id, meta_key, meta_value) VALUES
(@cel10, 'imei', '355678901234567'),
(@cel10, 'serial number', 'SN-SAM-M54-010'),
(@cel10, 'data_aquisicao', '2024-01-10'),
(@cel10, 'data_entrega', '2024-01-15'),
(@cel10, 'propriedade', 'Selbetti'),
(@cel10, 'selb', 'SELB-2024-010'),
(@cel10, 'observacao', 'Tela quebrada, aguardando orçamento de reparo.');

-- =====================================================
-- FIM DO SCRIPT - SUCESSO!
-- =====================================================
-- Total inserido:
-- ✓ 10 Colaboradores
-- ✓ 20 Metas de Colaboradores (setor e local)
-- ✓ 10 Celulares
-- ✓ 70 Metas de Celulares (7 campos x 10 celulares)
-- =====================================================
