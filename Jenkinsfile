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
    stage('DAST: OWASP ZAP (autenticado)') {
      steps {
        script {
          sh 'rm -rf zap && mkdir -p zap && chmod 777 zap'
          def full = (params.ZAP_MODE == 'full')
          def sitios = [
            [nombre: 'sitio1', puerto: '55340', cred: 'zap-sitio1', login: 'index.php',
             cuerpo: 'user={%username%}&pass={%password%}',
             dentro: '\\Qlogout.php\\E', fuera: 'name="pass"', logout: 'logout\\.php.*',
             semillas: ['admin/', 'admin/terminal.php', 'admin/destruct.php', 'system/', 'dev/']],
            [nombre: 'sitio2', puerto: '55350', cred: 'zap-sitio2', login: 'login.php',
             cuerpo: 'username={%username%}&password={%password%}',
             dentro: '\\Qlogout.php\\E', fuera: 'name="password"', logout: 'logout\\.php.*',
             semillas: ['private/', 'private/upload_map.php', 'mordor/wall.php']],
            [nombre: 'sitio3', puerto: '55360', cred: 'zap-sitio3', login: 'login.php',
             cuerpo: 'username={%username%}&password={%password%}',
             dentro: '\\Qlogout=1\\E', fuera: 'name="password"', logout: 'login\\.php\\?logout.*',
             semillas: ['admin/', 'admin/navigation.php', 'management/', 'shuttle/', 'wanted/']]
          ]
          def hayAlertas = false
          sitios.each { s ->
            def urls = ([''] + s.semillas).collect { "'http://localhost:${s.puerto}/${it}'" }.join(', ')
            def plan = '''env:
  contexts:
    - name: @@NAME@@
      urls: [@@URLS@@]
      includePaths: ['http://localhost:@@PORT@@.*']
      excludePaths: ['http://localhost:@@PORT@@/@@LOGOUT@@']
      authentication:
        method: form
        parameters:
          loginPageUrl: 'http://localhost:@@PORT@@/@@LOGIN@@'
          loginRequestUrl: 'http://localhost:@@PORT@@/@@LOGIN@@'
          loginRequestBody: '@@BODY@@'
        verification:
          method: response
          loggedInRegex: '@@IN@@'
          loggedOutRegex: '@@OUT@@'
      sessionManagement:
        method: cookie
      users:
        - name: usuario
          credentials:
            username: "${ZAP_USER}"
            password: "${ZAP_PASS}"
  parameters: { failOnError: true, failOnWarning: false, progressToStdout: true }
jobs:
  - type: spider
    parameters: { context: @@NAME@@, user: usuario, maxDuration: 1 }
  - type: passiveScan-wait
'''
            plan = plan.replace('@@NAME@@', s.nombre).replace('@@URLS@@', urls)
                       .replace('@@PORT@@', s.puerto).replace('@@LOGOUT@@', s.logout)
                       .replace('@@LOGIN@@', s.login).replace('@@BODY@@', s.cuerpo)
                       .replace('@@IN@@', s.dentro).replace('@@OUT@@', s.fuera)
            if (full) {
              plan += "  - type: activeScan\n    parameters: { context: ${s.nombre}, user: usuario, maxScanDurationInMins: 10 }\n"
            }
            ['traditional-html': 'html', 'traditional-json': 'json', 'traditional-md': 'md'].each { tpl, ext ->
              plan += "  - type: report\n    parameters: { template: ${tpl}, reportDir: /zap/wrk, reportFile: ${s.nombre}, reportTitle: 'ZAP ${s.nombre} autenticado' }\n    risks: [info, low, medium, high]\n"
            }
            writeFile file: "zap/plan-${s.nombre}.yaml", text: plan

            echo "ZAP ${full ? 'full' : 'baseline'} autenticado contra ${s.nombre}"
            withCredentials([usernamePassword(credentialsId: s.cred, usernameVariable: 'ZAP_USER', passwordVariable: 'ZAP_PASS')]) {
              def rc = sh(returnStatus: true, script: """
                docker rm -f ci_zap 2>/dev/null || true
                docker run --rm --name ci_zap --network host -e ZAP_USER -e ZAP_PASS \\
                  -v "\$WORKSPACE/zap":/zap/wrk:rw ${env.ZAP_IMAGE} \\
                  zap.sh -cmd -port 8099 -autorun /zap/wrk/plan-${s.nombre}.yaml > zap/${s.nombre}.log 2>&1
              """)
              if (rc != 0) { error("ZAP ha fallado en ${s.nombre} con código ${rc}") }
            }
            sh "grep -E 'spider found|activeScan finished' zap/${s.nombre}.log || true"
            def r = sh(returnStdout: true, script: """
              for n in 3 2 1 0; do printf '%s ' \$(grep -c '"riskcode": *"'\$n'"' zap/${s.nombre}.json || true); done
            """).trim().split(' ').collect { it as int }
            echo "${s.nombre}: High=${r[0]} Medium=${r[1]} Low=${r[2]} Info=${r[3]}"
            if (r[0] + r[1] + r[2] > 0) { hayAlertas = true }
          }
          if (hayAlertas) { unstable('ZAP ha encontrado alertas') }
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
