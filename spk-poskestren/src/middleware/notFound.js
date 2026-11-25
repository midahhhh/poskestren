const path = require('path');

const notFound = (req, res, next) => {
  res.status(404).sendFile(path.join(__dirname, '..', 'views', '404.html'));
};

module.exports = notFound;
