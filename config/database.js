// config/database.js - MySQL Connection Pool dengan mysql2/promise
const mysql = require('mysql2/promise');
require('dotenv').config();

const pool = mysql.createPool({
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'poskestren_albahjah',
    port: process.env.DB_PORT || 3306,
    waitForConnections: true,
    connectionLimit: 15,
    queueLimit: 0,
    charset: 'utf8mb4'
});

// Helper untuk query langsung dengan pembersihan otomatis
async function query(sql, params = []) {
    const [rows] = await pool.execute(sql, params);
    return rows;
}

module.exports = {
    pool,
    query
};
