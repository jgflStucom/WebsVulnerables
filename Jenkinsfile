pipeline {
  agent any
  options {
    timestamps()
    buildDiscarder(logRotator(numToKeepStr: '10'))
    disableConcurrentBuilds()
  }
  parameters {
    choice(name: 'ZAP_MODE', choices: ['baseline', 'full'],
           description: 'baseline: rápido y pasivo. full: añade ataque activo (lento).')
  }
  environment {
    SEMGREP_IMAGE = 'semgrep/semgrep:latest'
    ZAP_IMAGE     = 'ghcr.io/zaproxy/zaproxy:stable'
    LAB_COMPOSE   = 'docker compose -p ci-lab -f lab/docker-compose.yml -f compose.ci.yml'
    LAB_SITES     = 'sitio1:55340 sitio2:55350 sitio3:55360'
  }
  stages {
    stage('Checkout') {
      steps {
        sh 'rm -rf zap lab semgrep.json semgrep.sarif semgrep.txt compose.ci.yml'
        checkout scm
      }
    }
    stage('SAST: Semgrep') {
      steps {
        script {
          def rc = sh(returnStatus: true, script: '''
            docker run --rm --volumes-from jenkins -w "$WORKSPACE" ${SEMGREP_IMAGE} \
              semgrep scan \
              --config p/php --config p/owasp-top-ten \
              --config security/semgrep-php-custom.yml \
              --metrics=off --exclude db_data --exclude zap --exclude lab \
              --json-output=semgrep.json --sarif-output=semgrep.sarif \
              --text-output=semgrep.txt --error --text .
          ''')
          if (rc == 1) { unstable('Semgrep ha encontrado vulnerabilidades') }
          else if (rc != 0) { error("Semgrep ha fallado con código ${rc}") }
        }
      }
    }
    stage('Desplegar laboratorio') {
      steps {
        sh 'docker ps -aq --filter name=ci_ | xargs -r docker rm -f'
        sh 'cp -r ExamenWeb lab'
        writeFile file: 'compose.ci.yml', text: '''services:
  db1: { container_name: ci_mysql_1, restart: "no" }
  db2: { container_name: ci_mysql_2, restart: "no" }
  db3: { container_name: ci_mysql_3, restart: "no" }
  db4: { container_name: ci_mysql_4, restart: "no" }
  web1: { container_name: ci_web_1, ports: !override ["55340:80"] }
  web2: { container_name: ci_web_2, ports: !override ["55350:80"] }
  web3: { container_name: ci_web_3, ports: !override ["55360:80"] }
  web4: { container_name: ci_web_4, ports: !override ["55370:80"] }
'''
        sh '''
          ${LAB_COMPOSE} up -d --build
          for s in ${LAB_SITES}; do
            port=${s#*:}; ok=0
            for i in $(seq 1 40); do
              code=$(docker run --rm --network host curlimages/curl -s -o /dev/null -w '%{http_code}' http://localhost:$port/ || true)
              [ "$code" = "200" ] && ok=1 && break
              sleep 3
            done
            echo "${s%%:*} -> HTTP $code"
            [ "$ok" = "1" ] || { echo "El sitio ${s%%:*} no arranca"; exit 1; }
          done
        '''
      }
    }
    stage('DAST: OWASP ZAP') {
      steps {
        script {
          sh 'rm -rf zap && mkdir -p zap && chmod 777 zap'
          def script_zap = (params.ZAP_MODE == 'full') ? 'zap-full-scan.py' : 'zap-baseline.py'
          def extra = (params.ZAP_MODE == 'full') ? '-T 8 -z "-config scanner.maxScanDurationInMins=2"' : ''
          def peor = 0
          env.LAB_SITES.split(' ').each { s ->
            def (nombre, puerto) = s.split(':')
            def rc = sh(returnStatus: true, script: """
              docker run --rm --network host -v "\$WORKSPACE/zap":/zap/wrk:rw ${env.ZAP_IMAGE} \
                ${script_zap} -t http://localhost:${puerto} -m 1 ${extra} \
                -r ${nombre}.html -J ${nombre}.json -w ${nombre}.md > zap/${nombre}.log 2>&1
            """)
            echo "ZAP ${nombre}: código de salida ${rc}"
            if (rc > peor) { peor = rc }
          }
          if (peor == 1 || peor == 2) { unstable('ZAP ha encontrado alertas') }
          else if (peor != 0) { error("ZAP ha fallado con código ${peor}") }
        }
      }
    }
    stage('Informes') {
      steps {
        archiveArtifacts artifacts: 'semgrep.*,zap/*.html,zap/*.json,zap/*.md',
                         fingerprint: true, allowEmptyArchive: true
        recordIssues(tools: [sarif(pattern: 'semgrep.sarif', id: 'semgrep', name: 'Semgrep')],
                     enabledForFailure: true)
        publishHTML(target: [reportName: 'ZAP DAST', reportDir: 'zap',
                             reportFiles: 'sitio1.html,sitio2.html,sitio3.html',
                             reportTitles: 'Sitio 1,Sitio 2,Sitio 3',
                             keepAll: true, alwaysLinkToLastBuild: true, allowMissing: true])
      }
    }
  }
  post {
    always {
      sh '[ -f compose.ci.yml ] && ${LAB_COMPOSE} down --remove-orphans || true'
    }
  }
}
