pipeline {
    agent any

    environment {
        DOCKERHUB_CREDENTIALS = credentials('dockerhub-credentials')
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Test') {
            steps {
                bat 'docker build --target test -t antrisehat:test .'
            }
        }

        stage('Build Image') {
            steps {
                bat 'docker build -t %DOCKERHUB_CREDENTIALS_USR%/antrisehat:%BUILD_NUMBER% -t %DOCKERHUB_CREDENTIALS_USR%/antrisehat:latest .'
            }
        }

        stage('Push Image') {
            steps {
                bat '''
                    echo %DOCKERHUB_CREDENTIALS_PSW% | docker login -u %DOCKERHUB_CREDENTIALS_USR% --password-stdin
                    docker push %DOCKERHUB_CREDENTIALS_USR%/antrisehat:%BUILD_NUMBER%
                    docker push %DOCKERHUB_CREDENTIALS_USR%/antrisehat:latest
                '''
            }
        }

        stage('Deploy') {
            steps {
                echo 'Deployment stage will be configured'
            }
        }

        stage('Smoke Test') {
            steps {
                echo 'Smoke test stage will be configured'
            }
        }
    }

    post {
        success {
            echo 'Pipeline berhasil!'
        }

        failure {
            echo 'Pipeline gagal!'
        }
    }
}
