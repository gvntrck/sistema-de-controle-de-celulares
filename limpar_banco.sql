-- =====================================================
-- Script para limpar todas as tabelas do sistema
-- Sistema de Controle de Celulares
-- =====================================================
-- 
-- ATENÇÃO: Este script irá DELETAR TODOS OS DADOS!
-- Execute apenas se tiver certeza do que está fazendo.
-- 
-- Ordem de execução respeita as constraints de FK:
-- 1. Tabelas dependentes (com FK)
-- 2. Tabelas principais
-- =====================================================

-- Desabilitar verificação de chaves estrangeiras temporariamente
SET FOREIGN_KEY_CHECKS = 0;

-- Limpar tabelas dependentes primeiro
TRUNCATE TABLE wp_celulares_auditoria;
TRUNCATE TABLE wp_celulares_transferencias;
TRUNCATE TABLE wp_celulares_meta;
TRUNCATE TABLE wp_colaboradores_meta;

-- Limpar tabelas principais
TRUNCATE TABLE wp_celulares;
TRUNCATE TABLE wp_colaboradores;

-- Reabilitar verificação de chaves estrangeiras
SET FOREIGN_KEY_CHECKS = 1;

-- Resetar AUTO_INCREMENT (opcional)
ALTER TABLE wp_celulares AUTO_INCREMENT = 1;
ALTER TABLE wp_colaboradores AUTO_INCREMENT = 1;
ALTER TABLE wp_celulares_meta AUTO_INCREMENT = 1;
ALTER TABLE wp_colaboradores_meta AUTO_INCREMENT = 1;
ALTER TABLE wp_celulares_transferencias AUTO_INCREMENT = 1;
ALTER TABLE wp_celulares_auditoria AUTO_INCREMENT = 1;

-- Verificar se as tabelas estão vazias
SELECT 'wp_celulares' AS tabela, COUNT(*) AS registros FROM wp_celulares
UNION ALL
SELECT 'wp_colaboradores', COUNT(*) FROM wp_colaboradores
UNION ALL
SELECT 'wp_celulares_meta', COUNT(*) FROM wp_celulares_meta
UNION ALL
SELECT 'wp_colaboradores_meta', COUNT(*) FROM wp_colaboradores_meta
UNION ALL
SELECT 'wp_celulares_transferencias', COUNT(*) FROM wp_celulares_transferencias
UNION ALL
SELECT 'wp_celulares_auditoria', COUNT(*) FROM wp_celulares_auditoria;
