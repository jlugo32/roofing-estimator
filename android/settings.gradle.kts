pluginManagement {
    repositories {
        google()
        mavenCentral()
        gradlePluginPortal()
    }
}
dependencyResolutionManagement {
    repositoriesMode.set(RepositoriesMode.FAIL_ON_PROJECT_REPOS)
    repositories {
        google()
        mavenCentral()
    }
}

// RENAME BEFORE PLAY STORE SUBMISSION: project name is a placeholder (see docs/SETUP.md)
rootProject.name = "temp-project-android"
include(":app")
