const { name } = require('../package');

module.exports = (environment) => {
    const ENV = {
        modulePrefix: name,
        environment,
    };

    return ENV;
};
