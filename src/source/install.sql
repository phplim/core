/*
 Navicat Premium Data Transfer

 Source Server         : CD
 Source Server Type    : MySQL
 Source Server Version : 80024
 Source Host           : 1.14.96.20:6006
 Source Schema         : app

 Target Server Type    : MySQL
 Target Server Version : 80024
 File Encoding         : 65001

 Date: 30/10/2021 10:22:08
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for lim_api
-- ----------------------------
DROP TABLE IF EXISTS `lim_api`;
CREATE TABLE `lim_api`  (
  `id` int(0) NOT NULL AUTO_INCREMENT,
  `mid` int(0) NOT NULL DEFAULT 0,
  `top` int(0) NOT NULL DEFAULT 0,
  `class` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `method` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `url` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `name` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `speed` int(0) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 100 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lim_api
-- ----------------------------
INSERT INTO `lim_api` VALUES (1, 1, 0, '\\app\\api\\v1\\Api', '', '/api', '全局接口', 0, 1);
INSERT INTO `lim_api` VALUES (2, 2, 0, '\\app\\api\\v1\\Anchor', '', '/api/anchor', '主播接口', 0, 1);
INSERT INTO `lim_api` VALUES (3, 1, 2, '\\app\\api\\v1\\Anchor', 'insert', '/api/anchor/insert', '插入主播', 0, 1);
INSERT INTO `lim_api` VALUES (4, 2, 2, '\\app\\api\\v1\\Anchor', 'update', '/api/anchor/update', '更新主播', 0, 1);
INSERT INTO `lim_api` VALUES (10, 1, 1, '\\app\\api\\v1\\Api', 'register', '/api/register', '用户注册', 0, 1);
INSERT INTO `lim_api` VALUES (21, 2, 1, '\\app\\api\\Home', 'tt', '/api/tt', '用户登录', 0, 1);
INSERT INTO `lim_api` VALUES (86, 3, 2, '\\app\\api\\v1\\Anchor', 'status', '/api/anchor/status', '更新状态', 0, 1);
INSERT INTO `lim_api` VALUES (87, 4, 2, '\\app\\api\\v1\\Anchor', 'search', '/api/anchor/search', '主播查询', 0, 1);
INSERT INTO `lim_api` VALUES (88, 5, 2, '\\app\\api\\v1\\Anchor', 'recom', '/api/anchor/recom', '关注主播', 0, 1);
INSERT INTO `lim_api` VALUES (89, 6, 2, '\\app\\api\\v1\\Anchor', 'info', '/api/anchor/info', '主播详情', 0, 1);
INSERT INTO `lim_api` VALUES (90, 7, 2, '\\app\\api\\v1\\Anchor', 'total', '/api/anchor/total', '主播统计', 0, 1);
INSERT INTO `lim_api` VALUES (91, 8, 2, '\\app\\api\\v1\\Anchor', 'selector', '/api/anchor/selector', '主播选择器', 0, 1);
INSERT INTO `lim_api` VALUES (92, 3, 0, '\\app\\api\\v1\\Cate', '', '/api/cate', '分类接口', 0, 1);
INSERT INTO `lim_api` VALUES (93, 4, 0, '\\app\\api\\v1\\Guild', '', '/api/guild', '公会接口', 0, 1);
INSERT INTO `lim_api` VALUES (94, 1, 3, '\\app\\api\\v1\\Cate', 'search', '/api/cate/search', '分类查询', 0, 1);
INSERT INTO `lim_api` VALUES (95, 2, 3, '\\app\\api\\v1\\Cate', 'total', '/api/cate/total', '分类统计', 0, 1);
INSERT INTO `lim_api` VALUES (96, 2, 4, '\\app\\api\\v1\\Guild', 'total', '/api/guild/total', '公会统计', 0, 1);
INSERT INTO `lim_api` VALUES (97, 1, 4, '\\app\\api\\v1\\Guild', 'search', '/api/guild/search', '公会查询', 0, 1);
INSERT INTO `lim_api` VALUES (98, 3, 4, '\\app\\api\\v1\\Guild', 'recom', '/api/guild/recom', '关注公会', 0, 1);
INSERT INTO `lim_api` VALUES (99, 9, 2, '\\app\\api\\v1\\Anchor', 'wait', '/api/anchor/wait', '补全主播', 0, 1);

-- ----------------------------
-- Table structure for lim_auth
-- ----------------------------
DROP TABLE IF EXISTS `lim_auth`;
CREATE TABLE `lim_auth`  (
  `id` int(0) NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL DEFAULT NULL,
  `role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL DEFAULT NULL,
  `auth` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL DEFAULT NULL,
  `pass` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `id`(`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_bin ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lim_auth
-- ----------------------------
INSERT INTO `lim_auth` VALUES (1, '11', '1', '[2.9,4.3,4.2]', '$2y$10$eoOKxqLm431SZsgslmiSBuxA5PCfIcwOmN6qXlYHR642vgUPgqwn.');
INSERT INTO `lim_auth` VALUES (2, '11', '2', '[3,7,9]', '$2y$10$2ZZ0kWFWeeIUZaTgqtuNNe898d87zwiQT6KKfqGvOrdPzu3A7w8OG');

-- ----------------------------
-- Table structure for lim_config
-- ----------------------------
DROP TABLE IF EXISTS `lim_config`;
CREATE TABLE `lim_config`  (
  `id` int(0) NOT NULL,
  `mark` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `key` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `value` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `type` tinyint(1) NULL DEFAULT 1,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lim_config
-- ----------------------------
INSERT INTO `lim_config` VALUES (1, '程序名称', 'APP_NAME', 'lim', 1);
INSERT INTO `lim_config` VALUES (2, '程序端口', 'APP_HW_PORT', '6688', 1);
INSERT INTO `lim_config` VALUES (3, '工作进程数量', 'WORKER_NUM', '1', 1);
INSERT INTO `lim_config` VALUES (4, '任务进程数量', 'TASK_WORKER_NUM', '1', 1);
INSERT INTO `lim_config` VALUES (5, '最大协程数量', 'MAX_COROUTINE', '10000', 1);
INSERT INTO `lim_config` VALUES (6, 'token加密方式', 'TOKEN_ALGO', 'AES-128-CBC', 1);
INSERT INTO `lim_config` VALUES (7, 'token密钥', 'TOKEN_KEY', 'anchors.yuwan.cn', 1);
INSERT INTO `lim_config` VALUES (8, 'token向量', 'TOKEN_IV', 'anchors.yuwan.cn', 1);
INSERT INTO `lim_config` VALUES (9, 'token过期时间', 'TOKEN_EXP', '86400', 1);
INSERT INTO `lim_config` VALUES (10, 'token万能', 'TOKEN_FORCE', 'ywcm888.', 1);
INSERT INTO `lim_config` VALUES (11, 'token过滤', 'TOKEN_EXECEPT', '[\"login\", \"sregister\",\"slogin\"]', 1);

-- ----------------------------
-- Table structure for lim_role
-- ----------------------------
DROP TABLE IF EXISTS `lim_role`;
CREATE TABLE `lim_role`  (
  `id` int(0) NOT NULL,
  `name` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `auth` json NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lim_role
-- ----------------------------
INSERT INTO `lim_role` VALUES (1, '管理员', '[\"2.1\", \"2.2\", \"2.3\", \"2.4\", \"2.5\", \"2.6\", \"2.7\", \"2.8\", \"3.1\", \"4.1\", \"4.2\"]');

SET FOREIGN_KEY_CHECKS = 1;
