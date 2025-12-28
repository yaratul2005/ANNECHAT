const auth = {
    async register(username, email, password) {
        try {
            const response = await API.register(username, email, password);
            return { success: true, data: response.data, message: response.message };
        } catch (error) {
            return { success: false, error: error.message };
        }
    },

    async login(email, password) {
        try {
            const response = await API.login(email, password);
            return { success: true, user: response.data };
        } catch (error) {
            return { success: false, error: error.message };
        }
    },

    async guestLogin(username, age) {
        try {
            const response = await API.guestLogin(username, age);
            return { success: true, user: response.data };
        } catch (error) {
            return { success: false, error: error.message };
        }
    },

    async logout() {
        try {
            await API.logout();
            window.location.href = 'index.php';
        } catch (error) {
            console.error('Logout error:', error);
        }
    }
};

