FROM node:20

# Install build dependencies for native modules
RUN apt-get update && apt-get install -y python3 make g++ && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

# Install dependencies first (for better caching)
COPY package*.json ./
RUN npm install

# Install Playwright chromium browser + system dependencies for E2E tests
RUN npx playwright install --with-deps chromium

# Copy the rest of the code
COPY . .

# Expose port 3000
EXPOSE 3000

# Next.js dev server with hot reload
ENV WATCHPACK_POLLING=true
CMD ["npm", "run", "dev"]
