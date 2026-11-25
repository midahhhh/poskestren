const errorHandler = (err, req, res, next) => {
  const status = err.status || 500;
  const message = err.message || 'Terjadi kesalahan pada server.';

  console.error(`[ERROR] ${status} - ${message}`);
  if (process.env.NODE_ENV === 'development') {
    console.error(err.stack);
  }

  res.status(status).json({
    success: false,
    status,
    message,
    ...(process.env.NODE_ENV === 'development' && { stack: err.stack })
  });
};

module.exports = errorHandler;
