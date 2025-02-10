FROM nginx:1.27.4-bookworm

ARG ROOT
ARG ENV

# Copy project files from host
COPY $ROOT/config/nginx.$ENV.conf /etc/nginx/conf.d/default.conf

# Install build dependencies
RUN apt-get update && apt-get install -y \
    nano

# Run service
EXPOSE 80
ENTRYPOINT ["nginx", "-g", "daemon off;"]
